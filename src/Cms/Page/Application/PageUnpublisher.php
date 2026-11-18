<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRedirects;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Takes a published page off the site.
 */
final readonly class PageUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus, Transaction $transaction, PageRedirects $pageRedirects): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->unpublish($pageRedirects);

        $page->save($pageRepository, $eventBus, $transaction);
    }
}
