<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsSettingsImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Relates the settings to the images they use now, in place of those they
 * did.
 */
final readonly class SettingsImagesRelator
{
    public function __construct(private CmsSettingsImagesUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Settings, $this->event->aggregateId(), ResourceType::Image, $this->event->imageIds());
    }
}
