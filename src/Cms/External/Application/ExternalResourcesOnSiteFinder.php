<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Shared\Domain\DomainError;

/**
 * Finds those among the external resources given that the site paints, as
 * it paints them: published and available, in the version on the site,
 * keyed by id. Any other is left out, as is an id of no resource.
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
        $ids = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $ids[] = new ExternalResourceId($id);
            } catch (DomainError) {
                // Not the id of a resource: none is on the site.
            }
        }

        $found = [];

        foreach ($resourceRepository->findByIds($ids) as $resource) {
            if (ResourceStatus::Published === $resource->status() && Availability::Available === $resource->availability()) {
                $found[$resource->id()->value()] = ExternalResourceOnSiteResponse::fromResource($resource);
            }
        }

        return $found;
    }
}
