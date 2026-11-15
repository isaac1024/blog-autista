<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Shared\Domain\DomainError;

/**
 * Finds the external resources among the ids given, leaving out those of no
 * resource, and anything that can't be the id of one.
 */
final readonly class ExternalResourcesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<ExternalResourceResponse>
     */
    public function __invoke(ExternalResourceRepository $resourceRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new ExternalResourceId($id);
            } catch (DomainError) {
                // Not the id of a resource: none to find.
            }
        }

        return array_map(ExternalResourceResponse::fromResource(...), $resourceRepository->findByIds($ids));
    }
}
