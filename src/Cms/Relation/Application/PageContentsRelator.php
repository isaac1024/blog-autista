<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a page to the posts, sections and tags,
 * authors and external resources it links to with those it tells.
 */
final readonly class PageContentsRelator
{
    public function __construct(private CmsPageContentsLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $id = $this->event->aggregateId();
        $relationRepository->replace(ResourceType::Page, $id, ResourceType::Post, $this->event->postIds());
        $relationRepository->replace(ResourceType::Page, $id, ResourceType::Term, $this->event->termIds());
        $relationRepository->replace(ResourceType::Page, $id, ResourceType::Author, $this->event->authorIds());
        $relationRepository->replace(ResourceType::Page, $id, ResourceType::External, $this->event->externalIds());
    }
}
