<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectAtAddressFinder;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;

final class RedirectAtAddressFinderTest extends TestCase
{
    public function testItFindsTheRedirectThatAnswersTheAddressAsTheCmsShowsIt(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('findBySource')
            ->with(self::equalTo(Source::fromStored('/gu%c3%ada.html')))
            ->willReturn(RedirectMother::create(id: $id, source: '/gu%c3%ada.html'));

        self::assertEquals(
            new RedirectResponse($id, '/guía.html', false, 'page', RedirectMother::PAGE, null, null, ClockMother::now(), ClockMother::now()),
            new RedirectAtAddressFinder(Source::fromStored('/gu%c3%ada.html'))($repository),
        );
    }

    public function testItFindsNothingWhenNoRedirectAnswersTheAddress(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);

        self::assertNull(new RedirectAtAddressFinder(Source::fromStored('/guia.html'))($repository));
    }
}
