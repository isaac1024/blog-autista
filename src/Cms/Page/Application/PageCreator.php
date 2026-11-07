<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\SlugAvailability;
use Shared\Domain\Event\EventBus;

/**
 * Creates a page from the submitted title, deriving its slug from it.
 */
final readonly class PageCreator
{
    public function __construct(
        private string $id,
        private string $title,
    ) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus): void
    {
        $page = Page::create($this->id, $this->title);

        new SlugAvailability($page)($pageRepository);
        $page->save($pageRepository, $eventBus);
    }
}
