<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;

/**
 * Finds a page by its id, failing when it doesn't exist.
 */
final readonly class PageFinder
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository): PageResponse
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        return PageResponse::fromPage($page);
    }
}
