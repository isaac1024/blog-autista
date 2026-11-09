<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class ImagePublishControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/images/%s/publish';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $image = $this->saveImage(ImageMother::published(id: ImageIdMother::random()->value()));

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($image, $this->storedImage($image));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItPublishesTheSavedVersionAndGoesBackToTheLibrary(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        $image = $this->saveImage(ImageMother::create(id: $id, caption: ImageMother::CAPTION));

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, $id));

        self::assertResponseRedirects('/images', Response::HTTP_SEE_OTHER);
        self::assertSame([], self::notices($client));
        self::assertEquals(ImageMother::published(id: $id), $this->storedImage($image));
    }

    public function testItQueuesThePublicationForTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        $this->saveImage(ImageMother::create(id: $id, caption: ImageMother::CAPTION));

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, $id));

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.image.published', $events[0]->name);
        self::assertSame($id, $events[0]->aggregateId);
        self::assertSame(['alt' => ImageMother::ALT, 'caption' => ImageMother::CAPTION, 'path' => '2026/11/'.$id.'.webp', 'revision' => 1], $events[0]->body);
    }

    /**
     * @param array{key: string, parameters: array<string, int|string>} $notice
     */
    #[DataProvider('refusedImages')]
    public function testItGoesBackToTheEditorToTellWhyAndKeepsTheImage(Image $image, array $notice): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveImage($image);
        $expected = clone $image;

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/images/'.$image->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([$notice], self::notices($client));
        self::assertEquals($expected, $this->storedImage($image));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{Image, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function refusedImages(): iterable
    {
        yield 'a draft without a caption' => [
            ImageMother::create(id: ImageIdMother::random()->value()),
            ['key' => 'errors.image.publication.without_caption', 'parameters' => []],
        ];
        yield 'a published image as it is on the site' => [
            ImageMother::published(id: ImageIdMother::random()->value()),
            ['key' => 'errors.image.publication.nothing_to_publish', 'parameters' => []],
        ];
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_publish', \sprintf(self::URL, 'circulo-naranja'));

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

    private function saveImage(Image $image): Image
    {
        self::getContainer()->get(ImageRepository::class)->save($image);

        return $image;
    }

    /**
     * Reads the image back from the database, not from Doctrine's identity map.
     */
    private function storedImage(Image $image): ?Image
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ImageRepository::class)->find($image->id());
    }

    /**
     * The notices flashed for the page it goes back to, left for it to show.
     *
     * @return array<mixed>
     */
    private static function notices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }
}
