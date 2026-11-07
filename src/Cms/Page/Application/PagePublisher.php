<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\SlugAvailability;
use Shared\Domain\Event\EventBus;

/**
 * Saves what is edited on a page and puts it on the site, for the first time
 * or in place of the version there, in one go: publishing never leaves out
 * what the editor has just written.
 */
final readonly class PagePublisher
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
        $page->publish();

        new SlugAvailability($page)($pageRepository);
        $events = $page->pullDomainEvents();
        $page->save($pageRepository);
        $eventBus->publish(...$events);
    }
}
