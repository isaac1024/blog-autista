<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;

/**
 * The pages on the site among those given by id, as it serves them: a page
 * never published, or taken off, is left out, as is an id of no page.
 */
final readonly class PagesOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, PageOnSiteResponse> keyed by id
     */
    public function __invoke(PageRepository $pageRepository): array
    {
        $ids = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $ids[] = new PageId($id);
            } catch (DomainError) {
                // Not the id of a page: none on the site.
            }
        }

        $pages = [];

        foreach ($pageRepository->findByIds($ids) as $page) {
            $version = $page->onSite();

            if (null !== $version) {
                $pages[$page->id()->value()] = PageOnSiteResponse::fromVersion($version);
            }
        }

        return $pages;
    }
}
