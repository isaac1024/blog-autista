<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceFiller;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\Reading;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use Cms\Tests\External\Domain\ReadingMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceFillerTest extends TestCase
{
    use ClockSensitiveTrait;
    use ExternalResourceImageFile;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The image read takes the place of the one it had, which is removed
     * once the resource is saved.
     */
    public function testItFillsTheVersionBeingEditedFromWhatReadingItsAddressTells(): void
    {
        $kept = null;
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())->method('save')->with(self::callback(static function (ExternalResource $resource) use (&$kept): bool {
            return ['Un vídeo leído', 'De qué trata.', $kept] === [$resource->version()->title()->value(), $resource->version()->summary(), $resource->version()->image()?->value()];
        }));
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::once())->method('read')->with(self::equalTo(new ResourceUrl(ExternalResourceMother::VIDEO_URL)))->willReturn(ReadingMother::video());
        $fetcher = self::createStub(ResourceImageFetcher::class);
        $fetcher->method('fetch')->willReturn($this->landscapeImageFile());
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('save')->with(self::callback(static function (ResourceImagePath $image) use (&$kept): bool {
            $kept = $image->value();

            return true;
        }));
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new ResourceImagePath(ExternalResourceMother::IMAGE)));

        self::assertTrue(new ExternalResourceFiller(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $reader, $fetcher, $storage));
    }

    public function testItLeavesTheResourceAsItWasWhenTheAddressCouldNotBeReached(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())->method('save')->with(self::equalTo(ExternalResourceMother::video()));
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::video(reached: false));
        $fetcher = $this->createMock(ResourceImageFetcher::class);
        $fetcher->expects(self::never())->method('fetch');

        self::assertFalse(new ExternalResourceFiller(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $reader, $fetcher, self::createStub(ResourceImageStorage::class)));
    }

    public function testItRefusesWhatTheAddressTellsOfAnotherKindOfResource(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('save');
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::article());

        $this->expectExceptionObject(InvalidResourceUrl::anotherKind());

        new ExternalResourceFiller(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesWhatTheAddressTellsOfTheSameKindByAnotherProvider(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('save');
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(new Reading(ResourceKind::Video, 'tiktok', 'scout2015/6718335390845095173', 'Otro vídeo', null, null, [], [], true));

        $this->expectExceptionObject(InvalidResourceUrl::anotherKind());

        new ExternalResourceFiller(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceFiller(ExternalResourceMother::ID)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(ResourceReader::class), self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }
}
