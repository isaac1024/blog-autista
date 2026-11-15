<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;

/**
 * Finds the cookie policy as it is on the site, which what asks readers to
 * agree to keep something in their browsers leads to; none while it is off
 * the site.
 */
final readonly class CookiePolicyOnSiteFinder
{
    public function __invoke(PageRepository $pageRepository): ?PageOnSiteResponse
    {
        $version = $pageRepository->findByType(PageType::CookiePolicy)?->onSite();

        return null === $version ? null : PageOnSiteResponse::fromVersion($version);
    }
}
