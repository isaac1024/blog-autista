<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceUpdater;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string OTHER_VIDEO_URL = 'https://www.youtube.com/watch?v=aaaaaaaaaaa';

    private const string SAVED_AT = '2026-11-15 10:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now(self::SAVED_AT));
    }

    /**
     * Another address of the provider is another video of it, which the
     * provider names anew; the image stays.
     */
    public function testItSavesWhatTheEditorSetsInTheVersionBeingEdited(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::once())->method('findByUrl')->with(self::equalTo(new ResourceUrl(self::OTHER_VIDEO_URL)))->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $resource->version()->equals(ExternalResourceMother::version(ResourceKind::Video, self::OTHER_VIDEO_URL, 'aaaaaaaaaaa', 'Otro título', [], ExternalResourceMother::IMAGE, ''))
            && $resource->checked()->equals(ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Un vídeo', [], ExternalResourceMother::IMAGE))
            && ClockMother::now(self::SAVED_AT)->format(\DATE_ATOM) === $resource->timeline()->updatedAt()->format(\DATE_ATOM)));

        new ExternalResourceUpdater(ExternalResourceMother::ID, self::OTHER_VIDEO_URL, 'Otro título', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItKeepsTheAddressItHasAlready(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->method('findByUrl')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())->method('save');

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, 'Otro título', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressAnotherResourceHas(): void
    {
        $holder = '01a1e2a0-0000-7000-8000-000000000002';
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->method('findByUrl')->willReturn(ExternalResourceMother::video(id: $holder, url: self::OTHER_VIDEO_URL));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourceUrl::taken($holder));

        new ExternalResourceUpdater(ExternalResourceMother::ID, self::OTHER_VIDEO_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressOfAnotherKindOfResource(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourceUrl::anotherKind());

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::ARTICLE_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
