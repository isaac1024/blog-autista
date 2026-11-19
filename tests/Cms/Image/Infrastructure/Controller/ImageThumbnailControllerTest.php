<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Tests\Image\Domain\ImageFileMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The lists and pickers of the CMS draw an image a few hundred pixels wide:
 * they are given a copy that size, not the original.
 */
final class ImageThumbnailControllerTest extends CmsWebTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        foreach (['cms.image.storage_directory', 'cms.image.thumbnail_directory'] as $parameter) {
            $directory = self::getContainer()->getParameter($parameter);

            if (str_contains($directory, '/var/test/')) {
                new Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::url(ImageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesASmallCopyOfTheImageToAnAdministrator(): void
    {
        $client = self::createAuthenticatedClient();
        $image = ImageMother::create();
        self::getContainer()->get(ImageRepository::class)->save($image);
        self::getContainer()->get(ImageStorage::class)->save($image->path(), ImageFileMother::create());

        $client->request('GET', self::url($image->id()->value()));

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        $size = getimagesize($response->getFile()->getPathname());
        self::assertIsArray($size);
        self::assertSame([480, 270, 'image/webp'], [$size[0], $size[1], $size['mime']]);
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->getCacheControlDirective('private'));
        self::assertTrue($response->headers->getCacheControlDirective('immutable'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * Made the first time it is asked for, and kept: the next time is the
     * same file.
     */
    public function testItMakesTheCopyOnceAndGoesWithTheImage(): void
    {
        $client = self::createAuthenticatedClient();
        $image = ImageMother::create();
        self::getContainer()->get(ImageRepository::class)->save($image);
        $storage = self::getContainer()->get(ImageStorage::class);
        $storage->save($image->path(), ImageFileMother::create());

        $client->request('GET', self::url($image->id()->value()));
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        $thumbnail = $response->getFile()->getPathname();
        $made = filemtime($thumbnail);
        touch($thumbnail, (int) $made - 60);
        $client->request('GET', self::url($image->id()->value()));
        $kept = filemtime($thumbnail);
        $storage->delete($image->path());

        self::assertSame((int) $made - 60, $kept);
        self::assertFileDoesNotExist($thumbnail);
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::url(ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheImageHasNoFile(): void
    {
        $client = self::createAuthenticatedClient();
        $image = ImageMother::create(id: ImageIdMother::random()->value());
        self::getContainer()->get(ImageRepository::class)->save($image);

        $client->request('GET', self::url($image->id()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    private static function url(string $id): string
    {
        return 'http://cms.blogautista.localhost/images/'.$id.'/thumbnail';
    }
}
