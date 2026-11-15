<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;

/**
 * Finds every redirect that leads to a resource, on a single page: what
 * taking it off the site has to decide, all of it at once.
 */
final readonly class AllIncomingRedirectsFinder
{
    public function __construct(private Resource $resource) {}

    /**
     * @return list<RedirectResponse>
     */
    public function __invoke(RedirectRepository $redirectRepository): array
    {
        return array_map(RedirectResponse::fromRedirect(...), $redirectRepository->findTargeting($this->resource));
    }
}
