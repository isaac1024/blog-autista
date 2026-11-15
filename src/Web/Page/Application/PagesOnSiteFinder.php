<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Shared\Domain\DomainError;
use Web\Page\Domain\PageId;
use Web\Page\Domain\PageRepository;

/**
 * The pages on the site among those a rich text links to: a page the CMS took
 * off, or never put on, is left out, so its links render as plain text.
 */
final readonly class PagesOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, PageResponse> keyed by id
     */
    public function __invoke(PageRepository $pageRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new PageId($id);
            } catch (DomainError) {
                // Not the id of a page: none on the site.
            }
        }

        $pages = [];

        foreach ($pageRepository->findByIds($ids) as $page) {
            $response = PageResponse::fromPage($page);
            $pages[$response->id] = $response;
        }

        return $pages;
    }
}
