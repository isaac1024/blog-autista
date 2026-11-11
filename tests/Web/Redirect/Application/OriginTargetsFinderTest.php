<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Application;

use PHPUnit\Framework\TestCase;
use Web\Redirect\Application\OriginTargetsFinder;
use Web\Redirect\Domain\RedirectRepository;
use Web\Tests\Redirect\Domain\RedirectMother;

/**
 * Where the redirects of the last addresses of resources off the site lead,
 * by those resources; should two know one, the one changed last does, which
 * the repository gives first.
 */
final class OriginTargetsFinderTest extends TestCase
{
    private const string RETIRED = RedirectMother::ORIGIN;
    private const string GONE = '0196f3a0-0000-7000-8000-0000000000a3';

    public function testItTellsWhereTheLastAddressOfEachResourceLeads(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('findByOrigins')
            ->with('page', [self::RETIRED, self::GONE])
            ->willReturn([RedirectMother::create(originId: self::RETIRED), RedirectMother::gone(originId: self::GONE)]);

        self::assertSame(
            [self::RETIRED => ['type' => 'page', 'id' => RedirectMother::PAGE], self::GONE => null],
            new OriginTargetsFinder('page', [self::RETIRED, self::GONE])($repository),
        );
    }

    public function testTheRedirectChangedLastOfTwoThatKnowAResourceCounts(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findByOrigins')->willReturn([
            RedirectMother::gone(originId: self::RETIRED),
            RedirectMother::create(originId: self::RETIRED),
        ]);

        self::assertSame([self::RETIRED => null], new OriginTargetsFinder('page', [self::RETIRED])($repository));
    }

    /**
     * A redirect that knows no origin tells of none, and the rest still count.
     */
    public function testItLeavesOutARedirectThatKnowsNoOrigin(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findByOrigins')->willReturn([RedirectMother::create(), RedirectMother::gone(originId: self::RETIRED)]);

        self::assertSame([self::RETIRED => null], new OriginTargetsFinder('page', [self::RETIRED])($repository));
    }
}
