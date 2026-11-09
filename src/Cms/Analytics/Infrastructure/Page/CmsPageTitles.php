<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Page;

use Cms\Analytics\Domain\PageTitles;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that names the pages by the titles the CMS keeps.
 */
#[AsAlias(PageTitles::class)]
final readonly class CmsPageTitles implements PageTitles
{
    public function __construct(private PageRepository $pageRepository) {}

    #[\Override]
    public function titlesOf(array $pageIds): array
    {
        $ids = [];

        foreach ($pageIds as $pageId) {
            try {
                $ids[] = new PageId($pageId);
            } catch (DomainError) {
                // Not the id of a page: none to name.
            }
        }

        $titles = [];

        foreach ($this->pageRepository->findByIds($ids) as $page) {
            $titles[$page->id()->value()] = $page->title()->value();
        }

        return $titles;
    }
}
