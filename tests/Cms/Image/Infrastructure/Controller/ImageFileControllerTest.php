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

final class ImageFileControllerTest extends CmsWebTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        $directory = self::getContainer()->getParameter('cms.image.storage_directory');

        if (str_contains($directory, '/var/test/media')) {
            new Filesystem()->remove($directory);
        }

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::url(ImageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesTheOriginalToAnAdministrator(): void
    {
        $client = self::createAuthenticatedClient();
        $image = ImageMother::create();
        self::getContainer()->get(ImageRepository::class)->save($image);
        self::getContainer()->get(ImageStorage::class)->save($image->path(), ImageFileMother::create());

        $client->request('GET', self::url($image->id()->value()));

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->getCacheControlDirective('private'));
        // A file never changes: a new crop is a new image, with an id of its own.
        self::assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
        self::assertTrue($response->headers->getCacheControlDirective('immutable'));
        self::assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        self::assertFileEquals(ImageFileMother::path(), $response->getFile()->getPathname());
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::url(ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheFileDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();
        $image = ImageMother::create(id: ImageIdMother::random()->value());
        self::getContainer()->get(ImageRepository::class)->save($image);

        $client->request('GET', self::url($image->id()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/images/not-a-uuid/file');

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
        return 'http://cms.blogautista.localhost/images/'.$id.'/file';
    }
}
