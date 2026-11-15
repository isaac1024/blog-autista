<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceChecker;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\Availability;
use Cms\External\Domain\CheckOutcome;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceAvailabilityChanged;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceCheckerTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string CHECKED_AT = '2026-11-15 10:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now(self::CHECKED_AT));
    }

    /**
     * What the site has is what is checked, not what is being edited.
     */
    public function testItChecksTheAddressOnTheSiteAndTellsTheSiteWhenItIsGone(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'aaaaaaaaaaa', 'Un vídeo', [], ExternalResourceMother::IMAGE);
        $outcome = CheckOutcome::unavailable(404);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published, published: $onSite));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => Availability::Unavailable === $resource->availability() && ClockMother::now(self::CHECKED_AT)->format(\DATE_ATOM) === $resource->checkedAt()?->format(\DATE_ATOM)));
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::once())
            ->method('check')
            ->with(self::equalTo(new ResourceUrl('https://www.youtube.com/watch?v=aaaaaaaaaaa')), ResourceKind::Video, 'youtube', 'aaaaaaaaaaa')
            ->willReturn($outcome);
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::equalTo(new ExternalResourceAvailabilityChanged(ExternalResourceMother::ID, false, 2)));

        self::assertSame($outcome, new ExternalResourceChecker(ExternalResourceMother::ID)($repository, $eventBus, $reader, self::createStub(ResourceImageStorage::class)));
    }

    public function testItTellsTheSiteNothingOfAResourceNotOnIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => Availability::Unavailable === $resource->availability()));
        $reader = self::createStub(ResourceReader::class);
        $reader->method('check')->willReturn(CheckOutcome::unavailable(410));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (DomainEvent ...$events): void {
            self::assertSame([], $events);
        });

        new ExternalResourceChecker(ExternalResourceMother::ID)($repository, $eventBus, $reader, self::createStub(ResourceImageStorage::class));
    }

    /**
     * A doubtful answer, or a move, tells nothing of the availability, but
     * the check is dated all the same.
     */
    public function testItKeepsTheAvailabilityWhenTheCheckTellsNothingOfIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => Availability::Available === $resource->availability() && null !== $resource->checkedAt()));
        $reader = self::createStub(ResourceReader::class);
        $reader->method('check')->willReturn(CheckOutcome::doubtful(null));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (DomainEvent ...$events): void {
            self::assertSame([], $events);
        });

        new ExternalResourceChecker(ExternalResourceMother::ID)($repository, $eventBus, $reader, self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceChecker(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceReader::class), self::createStub(ResourceImageStorage::class));
    }
}
