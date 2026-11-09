<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;

/**
 * Finds the slug a page has on the site, or last had there once off it: null
 * for a page never published, for the home page, and for one whose last
 * address another page took.
 */
final readonly class PageLastAddressFinder
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository): ?string
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        return $page->lastSiteSlug()?->value();
    }
}
