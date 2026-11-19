<?php

declare(strict_types=1);

namespace Web\Tests\Image\Infrastructure\Storage;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Web\Image\Domain\ImageVariants;
use Web\Tests\Image\Domain\CmsImagePublishedMother;

/**
 * Runs against the directories the test environment configures, under
 * var/test, one per parallel process, which tearDown() empties: the real
 * files of the CMS and the copies the site serves are never touched.
 */
final class FilesystemImageVariantsTest extends KernelTestCase
{
    private const string PATH = CmsImagePublishedMother::PATH;

    #[\Override]
    protected function setUp(): void
    {
        // The file the CMS keeps, at its path.
        new Filesystem()->copy(CmsImagePublishedMother::file(), self::originals().'/'.self::PATH);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ([self::originals(), self::public()] as $directory) {
            if (str_contains($directory, '/var/test/')) {
                new Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    /**
     * The widest is the file of the CMS as it is; the others are made from
     * it, all 16:9 and readable by the web server.
     */
    public function testItMakesTheCopiesTheSiteServes(): void
    {
        self::variants()->publish(self::PATH);

        self::assertFileEquals(CmsImagePublishedMother::file(), self::copy(1200));

        foreach ([1200 => 675, 800 => 450, 400 => 225] as $width => $height) {
            $size = getimagesize(self::copy($width));
            self::assertIsArray($size);
            self::assertSame([$width, $height, 'image/webp'], [$size[0], $size[1], $size['mime']], \sprintf('%d wide', $width));
            self::assertSame(0o644, fileperms(self::copy($width)) & 0o777, \sprintf('%d wide', $width));
        }
    }

    /**
     * A file never changes, so a copy made once stays: publishing again makes
     * none of them anew.
     */
    public function testItKeepsTheCopiesAlreadyMade(): void
    {
        $variants = self::variants();
        $variants->publish(self::PATH);
        file_put_contents(self::copy(800), 'made before');

        $variants->publish(self::PATH);

        self::assertStringEqualsFile(self::copy(800), 'made before');
    }

    public function testItMakesTheCopiesStillMissing(): void
    {
        new Filesystem()->copy(CmsImagePublishedMother::file(), self::copy(1200));

        self::variants()->publish(self::PATH);

        self::assertFileExists(self::copy(800));
        self::assertFileExists(self::copy(400));
    }

    /**
     * A copy that fails halfway is never left in its place, where the site
     * would serve it for good and nothing would make it again.
     */
    public function testItLeavesNoCopyItFailedToMake(): void
    {
        new Filesystem()->dumpFile(self::originals().'/'.self::PATH, 'not an image');

        try {
            self::variants()->publish(self::PATH);
            self::fail('A file that is not an image makes no smaller copy.');
        } catch (\RuntimeException) {
        }

        // Nor the copy, nor what was written of it.
        self::assertSame(['.', '..'], scandir(\dirname(self::copy(800))));
    }

    public function testItRemovesOnlyTheCopiesOfTheImage(): void
    {
        $other = '2026/11/01a1b801-ce58-723d-8234-b76d9105d0ff.webp';
        new Filesystem()->copy(CmsImagePublishedMother::file(), self::originals().'/'.$other);
        $variants = self::variants();
        $variants->publish(self::PATH);
        $variants->publish($other);

        $variants->remove(self::PATH);

        foreach ([1200, 800, 400] as $width) {
            self::assertFileDoesNotExist(self::copy($width), \sprintf('%d wide', $width));
            self::assertFileExists(\sprintf('%s/2026/11/01a1b801-ce58-723d-8234-b76d9105d0ff-%dw.webp', self::public(), $width));
        }

        self::assertFileExists(self::originals().'/'.self::PATH, 'the file of the CMS stays');
    }

    public function testItDoesNothingWhenThereAreNoCopiesToRemove(): void
    {
        self::variants()->remove(self::PATH);

        self::assertDirectoryDoesNotExist(self::public().'/2026');
    }

    private static function variants(): ImageVariants
    {
        return self::getContainer()->get(ImageVariants::class);
    }

    private static function copy(int $width): string
    {
        return \sprintf('%s/2026/11/%s-%dw.webp', self::public(), basename(self::PATH, '.webp'), $width);
    }

    private static function originals(): string
    {
        return self::getContainer()->getParameter('web.image.originals_directory');
    }

    private static function public(): string
    {
        return self::getContainer()->getParameter('web.image.public_directory');
    }
}
