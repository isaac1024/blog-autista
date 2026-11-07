<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;

/**
 * Throws away what was edited on a published page since it was published,
 * back to the version on the site.
 */
final readonly class PageChangesDiscarder
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->discardChanges();

        $page->save($pageRepository);
    }
}
