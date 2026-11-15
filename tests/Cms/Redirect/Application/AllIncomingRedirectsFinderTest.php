<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\AllIncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;

final class AllIncomingRedirectsFinderTest extends TestCase
{
    private const string FIRST = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string SECOND = '0196f3a0-0000-7000-8000-0000000000e2';

    public function testItFindsEveryRedirectThatLeadsToTheResourceAsTheCmsShowsThem(): void
    {
        $resource = Resource::page(RedirectMother::PAGE);
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('findTargeting')
            ->with(self::equalTo($resource))
            ->willReturn([
                RedirectMother::create(id: self::FIRST, source: '/antes.html', target: $resource),
                RedirectMother::create(id: self::SECOND, source: '/mucho-antes.html', target: $resource),
            ]);

        self::assertEquals(
            [
                new RedirectResponse(self::FIRST, '/antes.html', false, 'page', RedirectMother::PAGE, null, null, ClockMother::now(), ClockMother::now()),
                new RedirectResponse(self::SECOND, '/mucho-antes.html', false, 'page', RedirectMother::PAGE, null, null, ClockMother::now(), ClockMother::now()),
            ],
            new AllIncomingRedirectsFinder($resource)($repository),
        );
    }

    public function testItFindsNoneWhenNothingLeadsToTheResource(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findTargeting')->willReturn([]);

        self::assertSame([], new AllIncomingRedirectsFinder(Resource::page(RedirectMother::PAGE))($repository));
    }
}
