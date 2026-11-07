<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\CmsPageUnpublished;
use Web\Page\Domain\PageId;
use Web\Page\Domain\PageRepository;

/**
 * Takes off the site a page the CMS unpublished. A page already gone stays
 * gone, so the same event can be taken twice.
 */
final readonly class PageRemover
{
    public function __construct(private CmsPageUnpublished $event) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $pageRepository->find(new PageId($this->event->aggregateId()))?->unpublish($this->event, $pageRepository);
    }
}
