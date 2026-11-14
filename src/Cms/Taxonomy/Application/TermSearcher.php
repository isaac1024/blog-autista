<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\NameFilter;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyFilter;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of the terms of a taxonomy, newest first, optionally
 * narrowed down to those whose name has the given text.
 */
final readonly class TermSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $taxonomy,
        private string $name,
        private int $page,
    ) {}

    public function __invoke(TermRepository $termRepository): TermsResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $name = trim($this->name);
        $filters = [TaxonomyFilter::equalTo(Taxonomy::fromValue($this->taxonomy))];

        if ('' !== $name) {
            $filters[] = NameFilter::contains($name);
        }

        // Term ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: $filters,
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        $terms = $termRepository->matching($criteria);

        return new TermsResponse(
            array_map(TermResponse::fromTerm(...), \array_slice($terms, 0, self::PAGE_SIZE)),
            \count($terms) > self::PAGE_SIZE,
        );
    }
}
