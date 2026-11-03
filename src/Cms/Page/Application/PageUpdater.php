<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\SlugAvailability;

/**
 * Saves the edited title and slug of an existing page.
 */
final readonly class PageUpdater
{
    public function __construct(
        private string $id,
        private string $title,
        private string $slug,
    ) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->edit($this->title, $this->slug);

        new SlugAvailability($page)($pageRepository);
        $page->save($pageRepository);
    }
}
