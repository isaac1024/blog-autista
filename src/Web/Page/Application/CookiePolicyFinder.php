<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\PageRepository;

/**
 * Finds the cookie policy of the site, which what asks readers to agree to
 * keep something in their browsers leads to; none while it is off the site.
 */
final readonly class CookiePolicyFinder
{
    public function __invoke(PageRepository $pageRepository): ?PageResponse
    {
        $page = $pageRepository->findCookiePolicy();

        return null === $page ? null : PageResponse::fromPage($page);
    }
}
