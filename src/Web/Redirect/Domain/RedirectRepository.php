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
}
