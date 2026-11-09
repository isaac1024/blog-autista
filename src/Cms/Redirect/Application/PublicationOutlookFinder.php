<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;

/**
 * Finds what publishing a resource as it is saved would do to the
 * redirects, before it is published.
 */
final readonly class PublicationOutlookFinder
{
    public function __construct(private Resource $resource) {}

    public function __invoke(RedirectRepository $redirectRepository, SiteResources $siteResources): PublicationOutlook
    {
        $next = $siteResources->nextAddressOf($this->resource);
        $current = $siteResources->addressOf($this->resource);
        $replaced = null === $next ? null : $redirectRepository->findBySource($next);

        // The redirect of its own former address goes without a word: it is
        // back where it was.
        if (true === $replaced?->origin()->resource()?->equals($this->resource)) {
            $replaced = null;
        }

        $previous = null === $current || null === $next || $current->equals($next) ? null : $current;
        $previousRedirect = null === $previous ? null : $redirectRepository->findBySource($previous);

        return new PublicationOutlook(
            $next?->shown(),
            null === $replaced ? null : RedirectResponse::fromRedirect($replaced),
            $previous?->shown(),
            null === $previousRedirect ? null : RedirectResponse::fromRedirect($previousRedirect),
        );
    }
}
