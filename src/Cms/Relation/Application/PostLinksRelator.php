<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPostPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a post to the pages its content links to with those it tells.
 */
final readonly class PostLinksRelator
{
    public function __construct(private CmsPostPagesLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Post, $this->event->aggregateId(), ResourceType::Page, $this->event->pageIds());
    }
}
