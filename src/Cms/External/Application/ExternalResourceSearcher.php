<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\Availability;
use Cms\External\Domain\AvailabilityFilter;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\TitleFilter;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of the external resources, newest first, optionally
 * narrowed down to those whose title has the given text, or to those whose
 * address no longer leads to them.
 */
final readonly class ExternalResourceSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $title,
        private bool $unavailableOnly,
        private int $page,
    ) {}

    public function __invoke(ExternalResourceRepository $resourceRepository): ExternalResourcesResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $title = trim($this->title);
        $filters = '' === $title ? [] : [TitleFilter::contains($title)];

        if ($this->unavailableOnly) {
            $filters[] = AvailabilityFilter::equalTo(Availability::Unavailable);
        }

        // Resource ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $resources = $resourceRepository->matching(new Criteria(
            filters: $filters,
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        ));

        return new ExternalResourcesResponse(
            array_map(ExternalResourceResponse::fromResource(...), \array_slice($resources, 0, self::PAGE_SIZE)),
            \count($resources) > self::PAGE_SIZE,
        );
    }
}
