<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceUnpublisher;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ExternalResourceUnpublished;
use Cms\External\Domain\InvalidResourcePublication;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;

final class ExternalResourceUnpublisherTest extends TestCase
{
    public function testItTakesTheResourceOffTheSiteAndTellsTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => ResourceStatus::Unpublished === $resource->status()));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::equalTo(new ExternalResourceUnpublished(ExternalResourceMother::ID, 2)));

        new ExternalResourceUnpublisher(ExternalResourceMother::ID)($repository, $eventBus, self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAResourceNotOnTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourcePublication::notPublished());

        new ExternalResourceUnpublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceUnpublisher(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
