<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\TitleFilter;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;

final class PageSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfPages(PageSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{PageSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = PageSearcher::PAGE_SIZE;

        yield 'no title, first page by default' => [
            new PageSearcher('', 1),
            new Criteria(limit: $pageSize, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'blank title does not filter' => [
            new PageSearcher('   ', 1),
            new Criteria(limit: $pageSize, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'title filters by its words' => [
            new PageSearcher('guía familias', 1),
            new Criteria(limit: $pageSize, filters: [TitleFilter::matches('guía familias')], order: $newestFirst, offset: 0),
        ];
        yield 'title is trimmed before filtering' => [
            new PageSearcher('  guía  ', 1),
            new Criteria(limit: $pageSize, filters: [TitleFilter::matches('guía')], order: $newestFirst, offset: 0),
        ];
        yield 'second page skips the first one' => [
            new PageSearcher('', 2),
            new Criteria(limit: $pageSize, filters: [], order: $newestFirst, offset: $pageSize),
        ];
        yield 'later pages skip every previous one' => [
            new PageSearcher('', 5),
            new Criteria(limit: $pageSize, filters: [], order: $newestFirst, offset: 4 * $pageSize),
        ];
        yield 'title and page together' => [
            new PageSearcher('guía', 3),
            new Criteria(limit: $pageSize, filters: [TitleFilter::matches('guía')], order: $newestFirst, offset: 2 * $pageSize),
        ];
    }

    public function testItReturnsTheFoundPagesInTheRepositoryOrder(): void
    {
        $guideId = PageIdMother::random()->value();
        $aboutId = PageIdMother::random()->value();
        $repository = self::createStub(PageRepository::class);
        $repository->method('matching')->willReturn([
            PageMother::create(id: $guideId, title: 'Guía rápida', slug: 'guia-rapida'),
            PageMother::create(id: $aboutId, title: 'Sobre mí', slug: 'sobre-mi'),
        ]);

        self::assertEquals(
            [
                new PageResponse($guideId, 'Guía rápida', 'guia-rapida', type: 'standard', home: false, unique: false, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
                new PageResponse($aboutId, 'Sobre mí', 'sobre-mi', type: 'standard', home: false, unique: false, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
            ],
            (new PageSearcher('', 1))($repository),
        );
    }

    public function testItReturnsNothingWhenNoPageMatches(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('matching')->willReturn([]);

        self::assertSame([], (new PageSearcher('inexistente', 1))($repository));
    }

    #[DataProvider('invalidPages')]
    public function testItRejectsPageNumbersBelowOne(int $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage($page));

        (new PageSearcher('', $page))($repository);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
