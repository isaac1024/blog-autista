<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AvatarFileMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Keeps and reads back the authors the controllers act on, and the files of
 * their avatars, apart for each test.
 */
abstract class AuthorControllerTestCase extends CmsWebTestCase
{
    protected const string HOST = 'http://cms.blogautista.localhost';

    #[\Override]
    protected function tearDown(): void
    {
        $directory = self::avatarDirectory();

        if (str_contains($directory, '/var/test/avatars')) {
            new Filesystem()->remove($directory);
        }

        parent::tearDown();
    }

    protected function saveAuthor(Author $author): Author
    {
        self::getContainer()->get(AuthorRepository::class)->save($author);

        return $author;
    }

    /**
     * Reads the author back from the database, not from Doctrine's identity map.
     */
    protected function storedAuthor(Author $author): ?Author
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(AuthorRepository::class)->find($author->id());
    }

    /**
     * Keeps a file at the avatar given, as an upload would have.
     */
    protected static function keepAvatar(string $avatar, string $file = AvatarFileMother::VALID): void
    {
        new Filesystem()->copy(AvatarFileMother::path($file), self::avatarDirectory().'/'.$avatar);
    }

    protected static function upload(string $name = AvatarFileMother::VALID): UploadedFile
    {
        return new UploadedFile(AvatarFileMother::path($name), $name, null, \UPLOAD_ERR_OK, true);
    }

    protected static function avatarDirectory(): string
    {
        return self::getContainer()->getParameter('cms.author.avatar_directory');
    }
}
