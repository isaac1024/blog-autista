<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsAuthorImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of an author to the images its bio uses with those
 * it tells.
 */
final readonly class AuthorImagesRelator
{
    public function __construct(private CmsAuthorImagesUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Author, $this->event->aggregateId(), ResourceType::Image, $this->event->imageIds());
    }
}
