<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * One page of the redirects that pass the filters, newest first.
 */
final readonly class RedirectsPage
{
    public const int PAGE_SIZE = 20;

    /**
     * @param list<Filter> $filters
     */
    public function __construct(
        private array $filters,
        private int $page,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository): RedirectsResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        // Redirect ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $redirects = $redirectRepository->matching(new Criteria(
            filters: $this->filters,
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        ));

        return new RedirectsResponse(
            array_map(RedirectResponse::fromRedirect(...), \array_slice($redirects, 0, self::PAGE_SIZE)),
            \count($redirects) > self::PAGE_SIZE,
        );
    }
}
