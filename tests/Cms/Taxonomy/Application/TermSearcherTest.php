<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermSearcher;
use Cms\Taxonomy\Application\TermsResponse;
use Cms\Taxonomy\Domain\InvalidTaxonomy;
use Cms\Taxonomy\Domain\NameFilter;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyFilter;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class TermSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfTheTermsOfTheTaxonomy(TermSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{TermSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = TermSearcher::PAGE_SIZE;
        // One more than a page tells whether another follows.
        $limit = $pageSize + 1;
        $sections = TaxonomyFilter::equalTo(Taxonomy::Section);
        $tags = TaxonomyFilter::equalTo(Taxonomy::Tag);

        yield 'the sections, first page' => [
            new TermSearcher('section', '', 1),
            new Criteria(limit: $limit, filters: [$sections], order: $newestFirst, offset: 0),
        ];
        yield 'blank name does not filter' => [
            new TermSearcher('tag', '   ', 1),
            new Criteria(limit: $limit, filters: [$tags], order: $newestFirst, offset: 0),
        ];
        yield 'name is trimmed before filtering' => [
            new TermSearcher('tag', ' te ', 1),
            new Criteria(limit: $limit, filters: [$tags, NameFilter::contains('te')], order: $newestFirst, offset: 0),
        ];
        yield 'later pages skip every previous one' => [
            new TermSearcher('section', 'vida', 3),
            new Criteria(limit: $limit, filters: [$sections, NameFilter::contains('vida')], order: $newestFirst, offset: 2 * $pageSize),
        ];
    }

    public function testItReturnsTheFoundTermsInTheRepositoryOrder(): void
    {
        $newer = TermMother::create(id: TermIdMother::random()->value(), name: 'Trabajo', slug: 'trabajo');
        $older = TermMother::create(id: TermIdMother::random()->value());
        $repository = self::createStub(TermRepository::class);
        $repository->method('matching')->willReturn([$newer, $older]);

        self::assertEquals(
            new TermsResponse([TermResponse::fromTerm($newer), TermResponse::fromTerm($older)], false),
            new TermSearcher('section', '', 1)($repository),
        );
    }

    #[DataProvider('pageSizes')]
    public function testItTellsWhetherMoreTermsFollow(int $found, int $listed, bool $hasMore): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('matching')->willReturn(array_map(
            static fn (int $index): Term => TermMother::create(id: TermIdMother::random()->value(), name: 'Sección '.$index, slug: 'seccion-'.$index),
            range(1, $found),
        ));

        $terms = new TermSearcher('section', '', 1)($repository);

        self::assertCount($listed, $terms->terms);
        self::assertSame($hasMore, $terms->hasMore);
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function pageSizes(): iterable
    {
        yield 'fewer than a page' => [3, 3, false];
        yield 'exactly a page' => [TermSearcher::PAGE_SIZE, TermSearcher::PAGE_SIZE, false];
        yield 'more than a page' => [TermSearcher::PAGE_SIZE + 1, TermSearcher::PAGE_SIZE, true];
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new TermSearcher('section', '', 0)($repository);
    }

    public function testItRefusesATaxonomyThatIsNone(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidTaxonomy::unknown('categoria'));

        new TermSearcher('categoria', '', 1)($repository);
    }
}
