<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyFilter;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Finds every term of a taxonomy, by name, such as the sections to pick one
 * from.
 */
final readonly class TermsOfTaxonomyFinder
{
    public function __construct(private Taxonomy $taxonomy) {}

    /**
     * @return list<TermResponse>
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $terms = [];
        $offset = 0;

        // A criteria reads a page at most, so it reads them one after another.
        do {
            $page = $termRepository->matching(new Criteria(
                limit: Criteria::MAX_LIMIT,
                filters: [TaxonomyFilter::equalTo($this->taxonomy)],
                order: new Order('name', OrderDirection::Ascending),
                offset: $offset,
            ));
            $terms = [...$terms, ...array_map(TermResponse::fromTerm(...), $page)];
            $offset += Criteria::MAX_LIMIT;
        } while (Criteria::MAX_LIMIT === \count($page));

        return $terms;
    }
}
