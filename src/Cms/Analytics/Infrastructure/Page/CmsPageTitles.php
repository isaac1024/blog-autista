<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Page;

use Cms\Analytics\Domain\PageTitles;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
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
        $titles = [];

        foreach (new PagesFinder($pageIds)($this->pageRepository) as $page) {
            $titles[$page->id] = $page->title;
        }

        return $titles;
    }
}
