<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceChangesDiscarder;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourcePublication;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The image only the version discarded had is removed once it is saved.
     */
    public function testItBringsTheResourceBackToTheVersionOnTheSite(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Un vídeo', [], ExternalResourceMother::IMAGE);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(title: 'Otro título', image: ExternalResourceMother::OTHER_IMAGE, status: ResourceStatus::Published, published: $onSite));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => $resource->version()->equals($onSite) && !$resource->hasUnpublishedChanges()));
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new ResourceImagePath(ExternalResourceMother::OTHER_IMAGE)));

        new ExternalResourceChangesDiscarder(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), $storage);
    }

    public function testItRefusesAResourceWithoutChangesToDiscard(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourcePublication::noChangesToDiscard());

        new ExternalResourceChangesDiscarder(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceChangesDiscarder(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
