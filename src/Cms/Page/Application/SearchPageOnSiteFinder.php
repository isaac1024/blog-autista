<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;

/**
 * Finds the page that searches the posts as it is on the site, which the
 * header of every page leads to; none while it is off the site.
 */
final readonly class SearchPageOnSiteFinder
{
    public function __invoke(PageRepository $pageRepository): ?PageOnSiteResponse
    {
        $version = $pageRepository->findByType(PageType::Search)?->onSite();

        return null === $version ? null : PageOnSiteResponse::fromVersion($version);
    }
}
