<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceDeleter;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ExternalResourceUsage;
use Cms\External\Domain\InvalidResourcePublication;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;

final class ExternalResourceDeleterTest extends TestCase
{
    public function testItDeletesTheResourceWithTheImagesOfBothItsVersions(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Un vídeo', [], ExternalResourceMother::OTHER_IMAGE);
        $resource = ExternalResourceMother::video(status: ResourceStatus::Unpublished, published: $onSite);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn($resource);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($resource));
        $deleted = [];
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::exactly(2))->method('delete')->willReturnCallback(static function (ResourceImagePath $image) use (&$deleted): void {
            $deleted[] = $image->value();
        });

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), $storage, self::createStub(ExternalResourceUsage::class));

        self::assertEqualsCanonicalizing([ExternalResourceMother::IMAGE, ExternalResourceMother::OTHER_IMAGE], $deleted);
    }

    public function testItRefusesAResourceOnTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidResourcePublication::publishedResource());

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class), self::createStub(ExternalResourceUsage::class));
    }

    public function testItRefusesAResourceSomethingUses(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('delete');
        $usage = $this->createMock(ExternalResourceUsage::class);
        $usage->expects(self::once())->method('isInUse')->with(self::equalTo(new ExternalResourceId(ExternalResourceMother::ID)))->willReturn(true);
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidResourcePublication::usedResource());

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), $storage, $usage);
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class), self::createStub(ExternalResourceUsage::class));
    }
}
