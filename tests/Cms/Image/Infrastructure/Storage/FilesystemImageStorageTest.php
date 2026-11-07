<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Storage;

use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageStorage;
use Cms\Tests\Image\Domain\ImageFileMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs against the storage the test environment configures: a directory of
 * its own under var/test, one per parallel process, so the real images are
 * never touched.
 */
final class FilesystemImageStorageTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        self::emptyStorage();

        parent::tearDown();
    }

    public function testItKeepsTheFileAtThePathOfTheImage(): void
    {
        self::storage()->save(ImageMother::path(), ImageFileMother::create());

        self::assertFileEquals(
            ImageFileMother::path(),
            self::directory().'/2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp',
        );
    }

    public function testItLeavesTheFileUploadedWhereItWas(): void
    {
        self::storage()->save(ImageMother::path(), ImageFileMother::create());

        self::assertFileExists(ImageFileMother::path());
    }

    public function testItRemovesOnlyTheFileAtThePathOfTheImage(): void
    {
        $other = ImageMother::path(id: ImageIdMother::random()->value());
        $storage = self::storage();
        $storage->save(ImageMother::path(), ImageFileMother::create());
        $storage->save($other, ImageFileMother::create());

        $storage->delete(ImageMother::path());

        self::assertFileDoesNotExist(self::directory().'/2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp');
        self::assertFileExists(self::directory().'/'.$other->value());
    }

    public function testItDoesNothingWhenThereIsNoFileToRemove(): void
    {
        $other = ImageMother::path(id: ImageIdMother::random()->value());
        $storage = self::storage();
        $storage->save($other, ImageFileMother::create());

        $storage->delete(ImageMother::path());

        self::assertFileExists(self::directory().'/'.$other->value());
    }

    /**
     * A path that points at a directory, as a wrong one could, never takes
     * the directory, nor what it holds, with it.
     */
    public function testItNeverRemovesADirectory(): void
    {
        $storage = self::storage();
        $storage->save(ImageMother::path(), ImageFileMother::create());

        $storage->delete(new ImagePath('2026'));
        $storage->delete(new ImagePath(''));

        self::assertFileExists(self::directory().'/2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp');
    }

    private static function storage(): ImageStorage
    {
        return self::getContainer()->get(ImageStorage::class);
    }

    private static function directory(): string
    {
        return self::getContainer()->getParameter('cms.image.storage_directory');
    }

    /**
     * Removes the test storage, and only it: anything else is left alone.
     */
    private static function emptyStorage(): void
    {
        $directory = self::directory();

        if (str_contains($directory, '/var/test/media')) {
            new Filesystem()->remove($directory);
        }
    }
}
