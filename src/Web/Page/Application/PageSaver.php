<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Web\Page\Domain\CmsPagePublished;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageId;
use Web\Page\Domain\PageRepository;

/**
 * Puts on the site the version of a page the CMS published, in place of the
 * one there, if any.
 */
final readonly class PageSaver
{
    public function __construct(private CmsPagePublished $event) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $page = $pageRepository->find(new PageId($this->event->aggregateId()));

        if (null === $page) {
            $page = Page::publish($this->event);
        } else {
            $page->republish($this->event);
        }

        $page->save($pageRepository);
    }
}
