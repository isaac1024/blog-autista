<?php

declare(strict_types=1);

namespace Web\External\Application;

use Web\External\Domain\ExternalResourceRepository;

/**
 * Finds those among the external resources given that the site paints: on
 * it and available, keyed by id. Any other is left out, so its text stays
 * plain and its widget isn't painted.
 */
final readonly class ExternalResourcesOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, ExternalResourceOnSiteResponse>
     */
    public function __invoke(ExternalResourceRepository $resourceRepository): array
    {
        return array_map(ExternalResourceOnSiteResponse::fromResource(...), $resourceRepository->availableAmong($this->ids));
    }
}
