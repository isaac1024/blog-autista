<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

/**
 * Finds the page at an address of the site by its slug: the one on the site
 * with it, or the one off the site whose last address it was. A slug that
 * can't be one has no page.
 */
final readonly class PageAtAddressFinder
{
    public function __construct(private string $slug) {}

    public function onSite(PageRepository $pageRepository): ?PageResponse
    {
        $slug = $this->slug();
        $page = null === $slug ? null : $pageRepository->findOnSiteBySlug($slug);

        return null === $page ? null : PageResponse::fromPage($page);
    }

    public function lastOffSite(PageRepository $pageRepository): ?PageResponse
    {
        $slug = $this->slug();
        $page = null === $slug ? null : $pageRepository->findOffSiteByLastSiteSlug($slug);

        return null === $page ? null : PageResponse::fromPage($page);
    }

    private function slug(): ?Slug
    {
        try {
            return new Slug($this->slug);
        } catch (DomainError) {
            return null;
        }
    }
}
