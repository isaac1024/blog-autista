<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\PageRepository;

/**
 * Finds the page on the site at a slug.
 */
final readonly class PageFinder
{
    public function __construct(private string $slug) {}

    public function __invoke(PageRepository $pageRepository): PageResponse
    {
        $page = $pageRepository->findBySlug($this->slug) ?? throw PageNotFound::withSlug($this->slug);

        return PageResponse::fromPage($page);
    }
}
