<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;

/**
 * Finds an external resource by its id, failing when it doesn't exist.
 */
final readonly class ExternalResourceFinder
{
    public function __construct(private string $id) {}

    public function __invoke(ExternalResourceRepository $resourceRepository): ExternalResourceResponse
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        return ExternalResourceResponse::fromResource($resource);
    }
}
