<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageUsage;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Deletes an existing page, failing when it doesn't exist.
 */
final readonly class PageDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(PageRepository $pageRepository, EventBus $eventBus, Transaction $transaction, PageUsage $pageUsage): void
    {
        $page = $pageRepository->find(new PageId($this->id)) ?? throw PageNotFound::withId($this->id);

        $page->delete($pageRepository, $eventBus, $transaction, $pageUsage);
    }
}
