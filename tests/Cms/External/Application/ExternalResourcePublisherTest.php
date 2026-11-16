<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourcePublisher;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourcePublished;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourcePublisherTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItPutsTheResourceOnTheSiteAndTellsTheSiteAllItShows(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => ResourceStatus::Published === $resource->status() && !$resource->hasSomethingToPublish()));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::equalTo(new ExternalResourcePublished(
            ExternalResourceMother::ID,
            'video',
            'youtube',
            ExternalResourceMother::VIDEO,
            ExternalResourceMother::VIDEO_URL,
            'Un vídeo',
            null,
            'es',
            [],
            ExternalResourceMother::IMAGE,
            'landscape',
            true,
            1,
        )));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, $eventBus, self::createStub(ResourceImageStorage::class));
    }

    /**
     * An address checked gone stays so while it is the one on the site;
     * another address is available until it is checked.
     */
    public function testItTakesANewAddressOnTheSiteAsAvailableUntilItIsChecked(): void
    {
        $published = ExternalResourceMother::version(ResourceKind::Video, 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'aaaaaaaaaaa', 'Un vídeo', [], ExternalResourceMother::IMAGE);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published, published: $published, availability: Availability::Unavailable));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => Availability::Available === $resource->availability()));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * A draft checked gone is published as it was found: its address is the
     * one checked, so the site isn't told to paint it.
     */
    public function testItKeepsWhatTheCheckOfADraftFoundWhenItPublishesTheAddressChecked(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(availability: Availability::Unavailable));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => Availability::Unavailable === $resource->availability()));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(static fn (ExternalResourcePublished $event): bool => false === $event->toPrimitives()['available']));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, $eventBus, self::createStub(ResourceImageStorage::class));
    }

    /**
     * The site takes the version saved, and the image only the version it
     * had used is removed once the resource is saved.
     */
    public function testItReplacesTheVersionOnTheSiteAndRemovesTheImageNoVersionHasAnyLonger(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Antes', [], ExternalResourceMother::OTHER_IMAGE);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published, published: $onSite));
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $resource->checked()->equals($resource->version()) && 'Un vídeo' === $resource->checked()->title()->value()));
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new ResourceImagePath(ExternalResourceMother::OTHER_IMAGE)));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), $storage);
    }

    /**
     * A post shows no image: the site is told it has none, nor a shape.
     */
    public function testItTellsTheSiteAPostHasNoImage(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::post());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(static fn (ExternalResourcePublished $event): bool => [null, null, 'social_post', 'bluesky'] === [$event->toPrimitives()['image'], $event->toPrimitives()['image_shape'], $event->toPrimitives()['kind'], $event->toPrimitives()['provider']]));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, $eventBus, self::createStub(ResourceImageStorage::class));
    }

    #[DataProvider('unpublishableResources')]
    public function testItPublishesNothingWithoutEverythingTheSiteShows(ExternalResource $resource, string $key): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn($resource);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class)));
    }

    /**
     * @return iterable<string, array{ExternalResource, string}>
     */
    public static function unpublishableResources(): iterable
    {
        yield 'a video without its image' => [ExternalResourceMother::video(image: null), 'errors.external.publication.without_image'];
        yield 'a post without who wrote it' => [ExternalResourceMother::post(details: ['author' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_author'];
        yield 'a post without its account' => [ExternalResourceMother::post(details: ['handle' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_author'];
        yield 'a post without its day' => [ExternalResourceMother::post(details: ['posted_on' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_date'];
        yield 'a post without its text' => [ExternalResourceMother::post(details: ['text' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_text'];
        yield 'a post of Bluesky by the handle of its account' => [ExternalResourceMother::post(url: 'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l', locator: 'bsky.app/3l6oveex3ii2l'), 'errors.external.publication.not_embeddable'];
        yield 'a study without its authors' => [ExternalResourceMother::study(details: ['authors' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_authors'];
        yield 'a study without its journal' => [ExternalResourceMother::study(details: ['journal' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_journal'];
        yield 'a study without its year' => [ExternalResourceMother::study(details: ['year' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_year'];
        yield 'a resource published as it is' => [ExternalResourceMother::article(status: ResourceStatus::Published), 'errors.external.publication.nothing_to_publish'];
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
