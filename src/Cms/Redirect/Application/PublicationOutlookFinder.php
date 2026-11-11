<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Publication;
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
        return PublicationOutlook::fromPublication(Publication::now($this->resource, $redirectRepository, $siteResources));
    }
}
