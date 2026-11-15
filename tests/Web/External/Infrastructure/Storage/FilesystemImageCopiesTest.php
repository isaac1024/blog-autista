<?php

declare(strict_types=1);

namespace Web\Tests\External\Infrastructure\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ImageShape;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\Filesystem\Filesystem;
use Web\External\Infrastructure\Storage\FilesystemImageCopies;

/**
 * The copies of the image of an external resource the site serves: one of
 * every width its shape takes, each of the height of the shape at it.
 */
final class FilesystemImageCopiesTest extends TestCase
{
    private const string IMAGE = '01a1e2a0-0000-7000-8000-0000000000a1.webp';

    private const string NAME = '01a1e2a0-0000-7000-8000-0000000000a1';

    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/external-copies-'.bin2hex(random_bytes(4));
        new Filesystem()->mkdir([$this->directory.'/originals', $this->directory.'/public']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    /**
     * @param array<int, int> $sizes width => height of each copy
     */
    #[DataProvider('shapes')]
    public function testItMakesACopyOfEveryWidthTheShapeTakes(ImageShape $shape, array $sizes): void
    {
        $this->original($shape);

        $this->copies()->publish(self::IMAGE, $shape);

        $made = [];

        $files = glob($this->directory.'/public/*.webp');

        foreach (false === $files ? [] : $files as $file) {
            $size = getimagesize($file);
            self::assertNotFalse($size);
            $made[basename($file)] = [$size['mime'], $size[0], $size[1]];
        }

        ksort($made);
        $expected = [];

        foreach ($sizes as $width => $height) {
            $expected[\sprintf('%s-%dw.webp', self::NAME, $width)] = ['image/webp', $width, $height];
        }

        ksort($expected);
        self::assertSame($expected, $made);
    }

    /**
     * @return iterable<string, array{ImageShape, array<int, int>}>
     */
    public static function shapes(): iterable
    {
        yield 'wide' => [ImageShape::Landscape, [400 => 225, 800 => 450, 1200 => 675]];
        yield 'upright' => [ImageShape::Portrait, [270 => 480, 540 => 960]];
        yield 'square' => [ImageShape::Square, [160 => 160, 320 => 320, 640 => 640]];
    }

    /**
     * Copies of a name never change, so those made are left as they are.
     */
    public function testItLeavesTheCopiesMadeAlready(): void
    {
        $this->original(ImageShape::Square);
        $kept = \sprintf('%s/public/%s-320w.webp', $this->directory, self::NAME);
        file_put_contents($kept, 'hecha antes');

        $this->copies()->publish(self::IMAGE, ImageShape::Square);

        self::assertSame('hecha antes', file_get_contents($kept));
        self::assertFileExists(\sprintf('%s/public/%s-160w.webp', $this->directory, self::NAME));
    }

    public function testItRemovesEveryCopyOfTheImageAndNoOther(): void
    {
        $this->original(ImageShape::Portrait);
        $this->copies()->publish(self::IMAGE, ImageShape::Portrait);
        $other = $this->directory.'/public/01a1e2a0-0000-7000-8000-0000000000a2-270w.webp';
        file_put_contents($other, 'otra');

        $this->copies()->remove(self::IMAGE, ImageShape::Portrait);

        self::assertSame([$other], glob($this->directory.'/public/*.webp'));
    }

    private function original(ImageShape $shape): void
    {
        $image = imagecreatetruecolor(1200, 1200);
        self::assertNotFalse($image);
        $cropped = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => $shape->width(), 'height' => $shape->height()]);
        self::assertNotFalse($cropped);
        imagewebp($cropped, $this->directory.'/originals/'.self::IMAGE);
    }

    private function copies(): FilesystemImageCopies
    {
        $filesystem = new LocalFilesystem(new Filesystem());

        return new FilesystemImageCopies($filesystem, new ImageResizer($filesystem), $this->directory.'/originals', $this->directory.'/public');
    }
}
