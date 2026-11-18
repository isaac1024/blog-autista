<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyFilter;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class TermsOfTaxonomyFinderTest extends TestCase
{
    /**
     * By name, in as many pages as the repository needs: a full page tells
     * another may follow, one short of it that none does.
     */
    public function testItFindsEveryTermOfTheTaxonomyByNameAcrossPages(): void
    {
        $first = array_map(static fn (int $i): Term => TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta '.$i, slug: 'etiqueta-'.$i), range(1, Criteria::MAX_LIMIT));
        $last = [TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Zzz', slug: 'zzz')];
        $asked = [];
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::exactly(2))
            ->method('matching')
            ->willReturnCallback(static function (Criteria $criteria) use (&$asked, $first, $last): array {
                $asked[] = $criteria;

                return 1 === \count($asked) ? $first : $last;
            });
        $tags = [TaxonomyFilter::equalTo(Taxonomy::Tag)];
        $byName = new Order('name', OrderDirection::Ascending);

        self::assertEquals(array_map(TermResponse::fromTerm(...), [...$first, ...$last]), new TermsOfTaxonomyFinder(Taxonomy::Tag->value)($repository));
        self::assertEquals([
            new Criteria(limit: Criteria::MAX_LIMIT, filters: $tags, order: $byName, offset: 0),
            new Criteria(limit: Criteria::MAX_LIMIT, filters: $tags, order: $byName, offset: Criteria::MAX_LIMIT),
        ], $asked);
    }

    public function testItFindsNoneWithoutTermsOfTheTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('matching')->willReturn([]);

        self::assertSame([], new TermsOfTaxonomyFinder(Taxonomy::Section->value)($repository));
    }
}
