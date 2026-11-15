<?php

declare(strict_types=1);

namespace Cms\External\Application;

/**
 * One page of external resources, and whether more follow.
 */
final readonly class ExternalResourcesResponse
{
    /**
     * @param list<ExternalResourceResponse> $resources
     */
    public function __construct(
        public array $resources,
        public bool $hasMore,
    ) {}
}
