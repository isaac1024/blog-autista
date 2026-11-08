<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPageImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Relates a page to the images it embeds now, in place of those it did.
 */
final readonly class PageImagesRelator
{
    public function __construct(private CmsPageImagesUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Page, $this->event->aggregateId(), ResourceType::Image, $this->event->imageIds());
    }
}
