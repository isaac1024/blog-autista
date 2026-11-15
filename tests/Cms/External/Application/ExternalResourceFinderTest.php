<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;

final class ExternalResourceFinderTest extends TestCase
{
    public function testItFindsTheResourceAsTheCmsShowsIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(new ExternalResourceId(ExternalResourceMother::ID)))
            ->willReturn(ExternalResourceMother::video());

        self::assertEquals(
            new ExternalResourceResponse(
                ExternalResourceMother::ID,
                'video',
                'youtube',
                ExternalResourceMother::VIDEO_URL,
                'Un vídeo',
                null,
                'es',
                [],
                ExternalResourceMother::IMAGE,
                'landscape',
                'draft',
                false,
                true,
                null,
                ExternalResourceMother::VIDEO_URL,
                ClockMother::now(),
                ClockMother::now(),
                true,
                false,
                true,
            ),
            new ExternalResourceFinder(ExternalResourceMother::ID)($repository),
        );
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceFinder(ExternalResourceMother::ID)($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('video'));

        new ExternalResourceFinder('video')($repository);
    }
}
