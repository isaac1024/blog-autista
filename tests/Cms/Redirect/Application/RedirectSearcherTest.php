<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectSearcher;
use Cms\Redirect\Application\RedirectsPage;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SourceFilter;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * The redirects are listed newest first, twenty a page, telling whether
 * more follow, optionally only those whose address has a text.
 */
final class RedirectSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfRedirects(RedirectSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{RedirectSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = RedirectsPage::PAGE_SIZE;
        // One more than a page tells whether another follows.
        $limit = $pageSize + 1;

        yield 'no text, first page' => [new RedirectSearcher('', 1), new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0)];
        yield 'blank text does not filter' => [new RedirectSearcher('   ', 1), new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0)];
        yield 'text written as the addresses are' => [new RedirectSearcher('  GUÍA ', 1), new Criteria(limit: $limit, filters: [SourceFilter::contains('GUÍA')], order: $newestFirst, offset: 0)];
        yield 'second page' => [new RedirectSearcher('', 2), new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: $pageSize)];
    }

    public function testItTellsAnotherPageFollowsWhenTheRepositoryHasOneMore(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('matching')->willReturn(self::redirects(RedirectsPage::PAGE_SIZE + 1));

        $page = new RedirectSearcher('', 1)($repository);

        self::assertSame([RedirectsPage::PAGE_SIZE, true, '/vieja-1.html', '/vieja-20.html'], [\count($page->redirects), $page->hasMore, $page->redirects[0]->source, $page->redirects[19]->source]);
    }

    public function testItTellsNoMoreFollowAFullPage(): void
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('matching')->willReturn(self::redirects(RedirectsPage::PAGE_SIZE));

        $page = new RedirectSearcher('', 1)($repository);

        self::assertSame([RedirectsPage::PAGE_SIZE, false], [\count($page->redirects), $page->hasMore]);
    }

    public function testItRejectsAPageBeforeTheFirst(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new RedirectSearcher('', 0)($repository);
    }

    /**
     * @return list<\Cms\Redirect\Domain\Redirect>
     */
    private static function redirects(int $count): array
    {
        return array_map(
            static fn (int $number) => RedirectMother::create(id: \sprintf('0196f3a0-0000-7000-8000-%012d', $number), source: \sprintf('/vieja-%d.html', $number)),
            range(1, $count),
        );
    }
}
