<?php

declare(strict_types=1);

namespace Web\External\Domain;

/**
 * Outbound port used to persist and read the external resources on the site.
 */
interface ExternalResourceRepository
{
    public function save(ExternalResource $resource): void;

    public function delete(ExternalResource $resource): void;

    public function find(ExternalResourceId $id): ?ExternalResource;

    /**
     * Those among the ids given that are on the site and available, keyed
     * by id.
     *
     * @param list<string> $ids
     *
     * @return array<string, ExternalResource>
     */
    public function availableAmong(array $ids): array;
}
