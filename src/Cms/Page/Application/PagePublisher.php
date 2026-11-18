<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Puts the version last saved of a page on the site, for the first time or in
 * place of the version there. What the editor hasn't saved yet stays out.
 *
 * Its slug was checked as it was saved, and stays reserved while the page is
 * edited, so no other page can have taken it since. A page off the site
 * that last had that address there loses it: it is this one's now.
 */
final readonly class PagePublisher
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->publish();
        $page->save($pageRepository, $eventBus, $transaction);

        // Its address is its last from now on, not that of a page off the
        // site that last had it.
        $slug = $page->publishedSlug();
        $former = null === $slug ? null : $pageRepository->findOffSiteByLastSiteSlug($slug);

        if (null !== $former && !$former->id()->equals($page->id())) {
            $former->loseLastAddress();
            $former->save($pageRepository, $eventBus, $transaction);
        }
    }
}
