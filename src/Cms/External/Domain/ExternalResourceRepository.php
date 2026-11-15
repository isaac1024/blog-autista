<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read external resources.
 */
interface ExternalResourceRepository
{
    /**
     * @throws InvalidResourceUrl when another resource is saved with the same address meanwhile
     */
    public function save(ExternalResource $resource): void;

    public function delete(ExternalResource $resource): void;

    public function find(ExternalResourceId $id): ?ExternalResource;

    /**
     * The resources among those given; an id of no resource is left out.
     *
     * @param list<ExternalResourceId> $ids
     *
     * @return list<ExternalResource>
     */
    public function findByIds(array $ids): array;

    /**
     * The resource edited with the address.
     */
    public function findByUrl(ResourceUrl $url): ?ExternalResource;

    /**
     * @return list<ExternalResource>
     */
    public function matching(Criteria $criteria): array;

    /**
     * How many resources on the site its address no longer leads to.
     */
    public function countUnavailable(): int;
}
