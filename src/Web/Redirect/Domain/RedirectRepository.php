<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

/**
 * Outbound port used to persist and read the redirects of the site.
 */
interface RedirectRepository
{
    public function save(Redirect $redirect): void;

    public function delete(Redirect $redirect): void;

    public function find(RedirectId $id): ?Redirect;

    /**
     * The redirect that answers the address: the one last changed, while
     * two do.
     */
    public function findBySource(string $source): ?Redirect;

    /**
     * The redirects that know any of the resources as their origin, the one
     * last changed first, so the first of each resource is the one that
     * counts while the site catches up with the CMS.
     *
     * @param string       $type "page" or "image"
     * @param list<string> $ids
     *
     * @return list<Redirect>
     */
    public function findByOrigins(string $type, array $ids): array;
}
