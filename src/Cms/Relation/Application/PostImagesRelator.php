<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPostImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a post to the images its content embeds with those it tells.
 */
final readonly class PostImagesRelator
{
    public function __construct(private CmsPostImagesUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Post, $this->event->aggregateId(), ResourceType::Image, $this->event->imageIds());
    }
}
