<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsImagePagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Relates an image to the pages its caption links to now, in place of those
 * it did.
 */
final readonly class ImageLinksRelator
{
    public function __construct(private CmsImagePagesLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Image, $this->event->aggregateId(), ResourceType::Page, $this->event->pageIds());
    }
}
