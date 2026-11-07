<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Event\EventBus;

/**
 * Puts the version last saved of a page on the site, for the first time or in
 * place of the version there. What the editor hasn't saved yet stays out.
 *
 * Its slug was checked as it was saved, and stays reserved while the page is
 * edited, so no other page can have taken it since.
 */
final readonly class PagePublisher
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->publish();

        $events = $page->pullDomainEvents();
        $page->save($pageRepository);
        $eventBus->publish(...$events);
    }
}
