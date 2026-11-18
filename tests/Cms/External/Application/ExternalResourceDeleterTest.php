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
use Shared\Tests\Domain\RecordingTransaction;

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

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $storage, self::createStub(ExternalResourceUsage::class));

        self::assertEqualsCanonicalizing([ExternalResourceMother::IMAGE, ExternalResourceMother::OTHER_IMAGE], $deleted);
    }

    public function testItRefusesAResourceOnTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidResourcePublication::publishedResource());

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(ResourceImageStorage::class), self::createStub(ExternalResourceUsage::class));
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

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $storage, $usage);
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(ResourceImageStorage::class), self::createStub(ExternalResourceUsage::class));
    }

    /**
     * Deleting it and telling it uses nothing any longer happen within one
     * transaction, so a subscriber told at once that fails undoes the
     * deletion.
     */
    public function testItDeletesTheResourceAndTellsWhatChangedWithinOneTransaction(): void
    {
        $transaction = new RecordingTransaction();
        $within = [];
        $note = static function () use ($transaction, &$within): void {
            $within[] = $transaction->isRunning();
        };
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->method('delete')->willReturnCallback($note);
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback($note);

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, $eventBus, $transaction, self::createStub(ResourceImageStorage::class), self::createStub(ExternalResourceUsage::class));

        self::assertSame([true, true], $within);
    }

    /**
     * When telling what changed fails, the resource stays, and its images
     * with it.
     */
    public function testItKeepsTheImagesWhenTellingFails(): void
    {
        $failure = new \RuntimeException('A subscriber failed.');
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willThrowException($failure);
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::never())->method('delete');

        $this->expectExceptionObject($failure);

        new ExternalResourceDeleter(ExternalResourceMother::ID)($repository, $eventBus, new RecordingTransaction(), $storage, self::createStub(ExternalResourceUsage::class));
    }
}
