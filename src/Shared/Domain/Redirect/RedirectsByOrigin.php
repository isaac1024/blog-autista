<?php

declare(strict_types=1);

namespace Shared\Domain\Redirect;

/**
 * Outbound port that tells where the redirects of the last addresses of
 * resources off the site lead, by those resources: the redirects that know
 * them as their origin.
 */
interface RedirectsByOrigin
{
    /**
     * Where the redirect of each resource leads: a resource by its type and
     * id, or null when it says the address is gone. A resource without one
     * is left out.
     *
     * @param string       $type such as "page" or "image"
     * @param list<string> $ids
     *
     * @return array<string, ?array{type: string, id: string}> keyed by the id of each resource off the site
     */
    public function targetsOf(string $type, array $ids): array;
}
