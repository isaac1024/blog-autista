<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPagePagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Relates a page to the pages it links to now, in place of those it did.
 */
final readonly class PageLinksRelator
{
    public function __construct(private CmsPagePagesLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Page, $this->event->aggregateId(), ResourceType::Page, $this->event->pageIds());
    }
}
