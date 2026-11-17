<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\PageRepository;

/**
 * Finds the page that searches the posts of the site, where a reader who
 * found nothing at an address can look for it; none while it is off the
 * site.
 */
final readonly class SearchPageFinder
{
    public function __invoke(PageRepository $pageRepository): ?PageResponse
    {
        $page = $pageRepository->findSearch();

        return null === $page ? null : PageResponse::fromPage($page);
    }
}
