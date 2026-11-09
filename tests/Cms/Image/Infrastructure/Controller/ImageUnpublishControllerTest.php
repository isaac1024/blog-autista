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

final class ImageUnpublishControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/images/%s/unpublish';

    /**
     * What its address does once off the site: nothing, so it isn't found.
     */
    private const array LEAVE_NOT_FOUND = ['address' => ['action' => 'none']];

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

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, $image->id()->value()), self::LEAVE_NOT_FOUND);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($image, $this->storedImage($image));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItTakesTheImageOffTheSiteAndStaysInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        $image = $this->saveImage(ImageMother::published(id: $id));

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, $id), self::LEAVE_NOT_FOUND);

        self::assertResponseRedirects('/images/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.image.unpublished', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(ImageMother::unpublished(id: $id), $this->storedImage($image));
    }

    public function testItQueuesTheUnpublicationForTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        $this->saveImage(ImageMother::published(id: $id));

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, $id), self::LEAVE_NOT_FOUND);

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.image.unpublished', $events[0]->name);
        self::assertSame($id, $events[0]->aggregateId);
        self::assertSame(['revision' => 2], $events[0]->body);
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

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, $image->id()->value()), self::LEAVE_NOT_FOUND);

        self::assertResponseRedirects('http://cms.blogautista.localhost/images/'.$image->id()->value().'/unpublish', Response::HTTP_SEE_OTHER);
        self::assertSame([$notice], self::notices($client));
        self::assertEquals($expected, $this->storedImage($image));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{Image, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function refusedImages(): iterable
    {
        $notice = ['key' => 'errors.image.publication.not_published', 'parameters' => []];

        yield 'a draft' => [ImageMother::create(id: ImageIdMother::random()->value()), $notice];
        yield 'an unpublished image' => [ImageMother::unpublished(id: ImageIdMother::random()->value()), $notice];
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, ImageIdMother::random()->value()), self::LEAVE_NOT_FOUND);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, 'circulo-naranja'), self::LEAVE_NOT_FOUND);

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
