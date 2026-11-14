<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPostAuthorsUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a post to the authors it is signed by with those it tells.
 */
final readonly class PostAuthorsRelator
{
    public function __construct(private CmsPostAuthorsUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Post, $this->event->aggregateId(), ResourceType::Author, $this->event->authorIds());
    }
}
