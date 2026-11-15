<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Keeps and reads back the external resources the controllers act on, the
 * files of their images, and what the sites they are read from answer,
 * apart for each test.
 */
abstract class ExternalResourceControllerTestCase extends CmsWebTestCase
{
    protected const string HOST = 'http://cms.blogautista.localhost';

    /**
     * An image of 640 by 480 pixels, as a provider tells one.
     */
    protected const string PNG = __DIR__.'/../Files/thumbnail.png';

    #[\Override]
    protected function tearDown(): void
    {
        $directory = self::imageDirectory();

        if (str_contains($directory, '/var/test/externals')) {
            new Filesystem()->remove($directory);
        }

        RecordedSites::forget();
        parent::tearDown();
    }

    protected function saveResource(ExternalResource $resource): ExternalResource
    {
        self::getContainer()->get(ExternalResourceRepository::class)->save($resource);

        return $resource;
    }

    /**
     * Reads the resource back from the database, not from Doctrine's identity map.
     */
    protected function storedResource(string $id): ?ExternalResource
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ExternalResourceRepository::class)->find(new \Cms\External\Domain\ExternalResourceId($id));
    }

    /**
     * Keeps a file at the image given, as reading a resource would have.
     */
    protected static function keepImage(string $image): void
    {
        new Filesystem()->dumpFile(self::imageDirectory().'/'.$image, 'webp');
    }

    protected static function imageDirectory(): string
    {
        return self::getContainer()->getParameter('cms.external.image_directory');
    }
}
