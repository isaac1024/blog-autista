<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read redirects.
 */
interface RedirectRepository
{
    /**
     * @throws InvalidRedirect when another redirect is saved with the same address meanwhile
     */
    public function save(Redirect $redirect): void;

    public function delete(Redirect $redirect): void;

    public function find(RedirectId $id): ?Redirect;

    public function findBySource(Source $source): ?Redirect;

    /**
     * Whether any redirect leads to the resource.
     */
    public function isTargeted(Resource $resource): bool;

    /**
     * Every redirect that leads to the resource.
     *
     * @return list<Redirect>
     */
    public function findTargeting(Resource $resource): array;

    /**
     * @return list<Redirect>
     */
    public function matching(Criteria $criteria): array;
}
