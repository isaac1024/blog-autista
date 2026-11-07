<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;

/**
 * Takes a published page off the site.
 */
final readonly class PageUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository, Clock $clock, EventBus $eventBus): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->unpublish($clock->now());

        $events = $page->pullDomainEvents();
        $page->save($pageRepository);
        $eventBus->publish(...$events);
    }
}
