<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageSlugAvailability;
use Shared\Domain\Event\EventBus;

/**
 * Saves everything edited on an existing page: its title, slug, content and
 * search engine settings. A published page stays on the site as it was until
 * it is published again.
 */
final readonly class PageUpdater
{
    /**
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public function __construct(
        private string $id,
        private string $title,
        private string $slug,
        private array $content,
        private string $metaDescription,
        private string $robots,
    ) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->edit($this->title, $this->slug, $this->content, $this->metaDescription, $this->robots);

        new PageSlugAvailability($page)($pageRepository);
        $page->save($pageRepository, $eventBus);
    }
}
