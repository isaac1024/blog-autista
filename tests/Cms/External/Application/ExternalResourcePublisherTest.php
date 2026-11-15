<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourcePublisher;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourcePublished;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourcePublication;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourcePublisherTest extends TestCase
{
    use ClockSensitiveTrait;

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

    public function testItRefusesAVideoWithoutTheImageItsFacadeShows(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(image: null));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourcePublication::withoutImage());

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAResourceOnTheSiteAsItIs(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourcePublication::nothingToPublish());

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
