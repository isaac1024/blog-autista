<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Storage;

use Cms\Author\Domain\AvatarFile;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Author\Domain\AvatarFileMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemAvatarStorageTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        self::emptyStorage();

        parent::tearDown();
    }

    public function testItKeepsTheFileUnderTheNameOfTheAvatar(): void
    {
        self::storage()->save(new AvatarPath(AuthorMother::AVATAR), AvatarFileMother::create());

        self::assertFileEquals(AvatarFileMother::path(), self::directory().'/'.AuthorMother::AVATAR);
        self::assertFileExists(AvatarFileMother::path(), 'the file uploaded stays where it was');
    }

    /**
     * An upload only its owner can read is kept readable by everyone, so the
     * process that serves it can.
     */
    public function testItKeepsTheFileReadableByTheWebServer(): void
    {
        new Filesystem()->mkdir(self::directory());
        $upload = self::directory().'/upload.webp';
        new Filesystem()->copy(AvatarFileMother::path(), $upload);
        chmod($upload, 0o600);

        self::storage()->save(new AvatarPath(AuthorMother::AVATAR), new AvatarFile($upload));

        self::assertSame(0o644, fileperms(self::directory().'/'.AuthorMother::AVATAR) & 0o777);
    }

    public function testItRemovesOnlyTheFileOfTheAvatar(): void
    {
        self::storage()->save(new AvatarPath(AuthorMother::AVATAR), AvatarFileMother::create());
        self::storage()->save(new AvatarPath(AuthorMother::OTHER_AVATAR), AvatarFileMother::create(AvatarFileMother::OTHER));

        self::storage()->delete(new AvatarPath(AuthorMother::AVATAR));

        self::assertFileDoesNotExist(self::directory().'/'.AuthorMother::AVATAR);
        self::assertFileExists(self::directory().'/'.AuthorMother::OTHER_AVATAR);
    }

    public function testItRemovesAFileAlreadyGoneWithoutFailing(): void
    {
        self::storage()->delete(new AvatarPath(AuthorMother::AVATAR));

        self::assertFileDoesNotExist(self::directory().'/'.AuthorMother::AVATAR);
    }

    private static function storage(): AvatarStorage
    {
        return self::getContainer()->get(AvatarStorage::class);
    }

    private static function directory(): string
    {
        $directory = self::getContainer()->getParameter('cms.author.avatar_directory');
        self::assertIsString($directory);

        return $directory;
    }

    private static function emptyStorage(): void
    {
        $directory = self::directory();

        if (str_contains($directory, '/var/test/avatars')) {
            new Filesystem()->remove($directory);
        }
    }
}
