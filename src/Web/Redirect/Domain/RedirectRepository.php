<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

/**
 * Outbound port used to read the redirects of the site, as the CMS keeps them.
 */
interface RedirectRepository
{
    /**
     * The redirect that answers the address, if any.
     */
    public function findBySource(string $source): ?Redirect;

    /**
     * The redirects that know any of the resources as their origin, the one
     * last changed first, so the first of each resource is the one that counts.
     *
     * @param string       $type "page" or "image"
     * @param list<string> $ids
     *
     * @return list<Redirect>
     */
    public function findByOrigins(string $type, array $ids): array;
}
