<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsSettingsPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Relates the settings to the pages their menus lead to now, in place of
 * those they did.
 */
final readonly class SettingsLinksRelator
{
    public function __construct(private CmsSettingsPagesLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Settings, $this->event->aggregateId(), ResourceType::Page, $this->event->pageIds());
    }
}
