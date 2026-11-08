<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Image\Domain\ImageFileMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Files land in the storage the test environment configures, under var/test,
 * which tearDown() empties: the real images are never touched.
 */
final class ImageDeleteControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/%s/delete';

    #[\Override]
    protected function tearDown(): void
    {
        $directory = self::directory();

        if (str_contains($directory, '/var/test/media')) {
            new Filesystem()->remove($directory);
        }

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $image = $this->saveImage(ImageMother::create(id: ImageIdMother::random()->value()));

        $client->request('POST', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($image, $this->storedImage($image));
        self::assertFileExists(self::file($image));
    }

    #[DataProvider('imagesOffTheSite')]
    public function testItDeletesTheImageWithItsFileAndGoesBackToTheLibrary(Image $image): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveImage($image);

        $client->request('POST', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('/images', Response::HTTP_SEE_OTHER);
        self::assertNull($this->storedImage($image));
        self::assertFileDoesNotExist(self::file($image));
    }

    /**
     * @return iterable<string, array{Image}>
     */
    public static function imagesOffTheSite(): iterable
    {
        yield 'a draft' => [ImageMother::create(id: ImageIdMother::random()->value())];
        yield 'an unpublished image' => [ImageMother::unpublished(id: ImageIdMother::random()->value())];
    }

    public function testItGoesBackToTheEditorToTellWhyAndKeepsAPublishedImage(): void
    {
        $client = self::createAuthenticatedClient();
        $image = $this->saveImage(ImageMother::published(id: ImageIdMother::random()->value()));
        $expected = clone $image;

        $client->request('POST', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/images/'.$image->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.image.publication.published_image', 'parameters' => []]], self::notices($client));
        self::assertEquals($expected, $this->storedImage($image));
        self::assertFileExists(self::file($image));
    }

    public function testItGoesBackToTheEditorToTellWhyAndKeepsAnImageInUse(): void
    {
        $client = self::createAuthenticatedClient();
        $image = $this->saveImage(ImageMother::unpublished(id: ImageIdMother::random()->value()));
        $expected = clone $image;
        self::getContainer()->get(RelationRepository::class)->replace(
            ResourceType::Page,
            '01a19e72-b07f-7d78-889c-fa4a14a4fb95',
            ResourceType::Image,
            [$image->id()->value()],
        );

        $client->request('POST', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/images/'.$image->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.image.publication.in_use', 'parameters' => []]], self::notices($client));
        self::assertEquals($expected, $this->storedImage($image));
        self::assertFileExists(self::file($image));
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, 'circulo-naranja'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * Saves the image with its file kept in the storage.
     */
    private function saveImage(Image $image): Image
    {
        self::getContainer()->get(ImageStorage::class)->save($image->path(), ImageFileMother::create());
        self::getContainer()->get(ImageRepository::class)->save($image);

        return $image;
    }

    private function storedImage(Image $image): ?Image
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ImageRepository::class)->find($image->id());
    }

    private static function file(Image $image): string
    {
        return self::directory().'/'.$image->path()->value();
    }

    private static function directory(): string
    {
        return self::getContainer()->getParameter('cms.image.storage_directory');
    }

    /**
     * @return array<mixed>
     */
    private static function notices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }
}
