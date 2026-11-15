<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceCreator;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use Cms\Tests\External\Domain\ReadingMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use ExternalResourceImageFile;

    private const string OWN_HOST = 'www.blogautista.es';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The image read is kept under a name of its own, as the image of the
     * draft.
     */
    public function testItStartsADraftFromWhatReadingItsAddressTells(): void
    {
        $file = $this->landscapeImageFile();
        $kept = null;
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (ExternalResource $resource) use (&$kept): bool {
                return [ExternalResourceMother::ID, ResourceKind::Video, 'youtube', ResourceStatus::Draft, 'Un vídeo leído', ExternalResourceMother::VIDEO, $kept]
                    === [$resource->id()->value(), $resource->kind(), $resource->provider(), $resource->status(), $resource->version()->title()->value(), $resource->version()->locator(), $resource->version()->image()?->value()];
            }));
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::once())->method('read')->with(self::equalTo(new ResourceUrl(ExternalResourceMother::VIDEO_URL)))->willReturn(ReadingMother::video());
        $fetcher = $this->createMock(ResourceImageFetcher::class);
        $fetcher->expects(self::once())->method('fetch')->with(ReadingMother::IMAGE_URL, ImageShape::Landscape)->willReturn($file);
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('save')->with(self::callback(static function (ResourceImagePath $image) use (&$kept): bool {
            $kept = $image->value();

            return true;
        }), self::identicalTo($file));
        $storage->expects(self::never())->method('delete');

        self::assertTrue(new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, $fetcher, $storage));
    }

    public function testItTellsWhenTheAddressCouldNotBeReached(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())->method('save');
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::video(images: [], reached: false));

        self::assertFalse(new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class)));
    }

    /**
     * Saving fails, so the image kept for it is left behind by no one.
     */
    public function testItRemovesTheImageKeptWhenTheResourceCannotBeSaved(): void
    {
        $failure = new \RuntimeException('The database is gone.');
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->method('save')->willThrowException($failure);
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::video());
        $fetcher = self::createStub(ResourceImageFetcher::class);
        $fetcher->method('fetch')->willReturn($this->landscapeImageFile());
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('save');
        $storage->expects(self::once())->method('delete');

        $this->expectExceptionObject($failure);

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, $fetcher, $storage);
    }

    public function testItRefusesAnAddressOfTheBlogItselfWithoutReadingIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('save');
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        $this->expectExceptionObject(InvalidResourceUrl::ownSite());

        new ExternalResourceCreator(ExternalResourceMother::ID, 'https://blogautista.es/sobre-mi.html', self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressAnotherResourceHasWithoutReadingIt(): void
    {
        $holder = '01a1e2a0-0000-7000-8000-000000000002';
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())->method('findByUrl')->with(self::equalTo(new ResourceUrl(ExternalResourceMother::VIDEO_URL)))->willReturn(ExternalResourceMother::video(id: $holder));
        $repository->expects(self::never())->method('save');
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        $this->expectExceptionObject(InvalidResourceUrl::taken($holder));

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }
}
