<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsImageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a image to the posts, sections and tags, and
 * authors it links to with those it tells.
 */
final readonly class ImageContentsRelator
{
    public function __construct(private CmsImageContentsLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $id = $this->event->aggregateId();
        $relationRepository->replace(ResourceType::Image, $id, ResourceType::Post, $this->event->postIds());
        $relationRepository->replace(ResourceType::Image, $id, ResourceType::Term, $this->event->termIds());
        $relationRepository->replace(ResourceType::Image, $id, ResourceType::Author, $this->event->authorIds());
    }
}
