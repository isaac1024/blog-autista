<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\PageRepository;

/**
 * Finds the home page of the site, served at its root.
 */
final readonly class HomePageFinder
{
    public function __invoke(PageRepository $pageRepository): PageResponse
    {
        $page = $pageRepository->findHome() ?? throw PageNotFound::home();

        return PageResponse::fromPage($page);
    }
}
