<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\OriginTargetsFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;

/**
 * Where the redirects of the last addresses of resources off the site lead,
 * by those resources.
 */
final class OriginTargetsFinderTest extends TestCase
{
    private const string RETIRED = RedirectMother::OFF_SITE;
    private const string GONE = '0196f3a0-0000-7000-8000-0000000000a3';

    public function testItTellsWhereTheLastAddressOfEachResourceLeads(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('findByOrigins')
            ->with(ResourceType::Page, [self::RETIRED, self::GONE])
            ->willReturn([
                RedirectMother::create(id: '0196f3a0-0000-7000-8000-0000000000e1', source: '/retirada.html', origin: Resource::page(self::RETIRED)),
                RedirectMother::gone(id: '0196f3a0-0000-7000-8000-0000000000e2', source: '/borrada.html', origin: Resource::page(self::GONE)),
            ]);

        self::assertSame(
            [self::RETIRED => ['type' => 'page', 'id' => RedirectMother::PAGE], self::GONE => null],
            new OriginTargetsFinder(ResourceType::Page, [self::RETIRED, self::GONE])($repository),
        );
    }

    /**
     * A redirect that knows no origin tells of none.
     */
    public function testItLeavesOutARedirectThatKnowsNoOrigin(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findByOrigins')->willReturn([
            RedirectMother::create(),
            RedirectMother::gone(id: '0196f3a0-0000-7000-8000-0000000000e2', source: '/retirada.html', origin: Resource::page(self::RETIRED)),
        ]);

        self::assertSame([self::RETIRED => null], new OriginTargetsFinder(ResourceType::Page, [self::RETIRED])($repository));
    }
}
