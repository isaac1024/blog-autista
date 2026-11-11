<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectFinder;
use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;

final class RedirectFinderTest extends TestCase
{
    public function testItFindsTheRedirectWithTheGivenIdAsTheCmsShowsIt(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(RedirectIdMother::create($id)))
            ->willReturn(RedirectMother::create(id: $id, source: '/gu%c3%ada.html', origin: Resource::page(RedirectMother::OFF_SITE), updatedAt: '2026-11-08 10:00:00'));

        self::assertEquals(
            new RedirectResponse($id, '/guía.html', false, 'page', RedirectMother::PAGE, 'page', RedirectMother::OFF_SITE, ClockMother::now(), ClockMother::now('2026-11-08 10:00:00')),
            new RedirectFinder($id)($repository),
        );
    }

    /**
     * The address of an image is shown as its largest copy, and the redirect
     * as gone when it leads nowhere.
     */
    public function testItShowsTheAddressOfAnImageAsItsLargestCopy(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('find')->willReturn(RedirectMother::gone(id: $id, source: '/media/2026/11/'.RedirectMother::IMAGE));

        self::assertEquals(
            new RedirectResponse($id, '/media/2026/11/'.RedirectMother::IMAGE.'-1200w.webp', true, null, null, null, null, ClockMother::now(), ClockMother::now()),
            new RedirectFinder($id)($repository),
        );
    }

    public function testItThrowsWhenTheRedirectDoesNotExist(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(RedirectNotFound::withId($id));

        new RedirectFinder($id)($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('redirect-id'));

        new RedirectFinder('redirect-id')($repository);
    }
}
