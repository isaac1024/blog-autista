<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceRepository;

/**
 * Counts the external resources on the site whose address no longer leads
 * to them, which the site paints nowhere.
 */
final readonly class UnavailableResourcesCounter
{
    public function __invoke(ExternalResourceRepository $resourceRepository): int
    {
        return $resourceRepository->countUnavailable();
    }
}
