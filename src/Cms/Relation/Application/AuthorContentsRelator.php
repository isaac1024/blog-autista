<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsAuthorContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a author to the posts, sections and tags, and
 * authors it links to with those it tells.
 */
final readonly class AuthorContentsRelator
{
    public function __construct(private CmsAuthorContentsLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $id = $this->event->aggregateId();
        $relationRepository->replace(ResourceType::Author, $id, ResourceType::Post, $this->event->postIds());
        $relationRepository->replace(ResourceType::Author, $id, ResourceType::Term, $this->event->termIds());
        $relationRepository->replace(ResourceType::Author, $id, ResourceType::Author, $this->event->authorIds());
    }
}
