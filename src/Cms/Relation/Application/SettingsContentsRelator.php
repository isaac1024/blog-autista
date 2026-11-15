<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsSettingsContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of the settings to the posts, sections and tags,
 * authors and external resources their menus lead to with those they tell.
 */
final readonly class SettingsContentsRelator
{
    public function __construct(private CmsSettingsContentsLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $id = $this->event->aggregateId();
        $relationRepository->replace(ResourceType::Settings, $id, ResourceType::Post, $this->event->postIds());
        $relationRepository->replace(ResourceType::Settings, $id, ResourceType::Term, $this->event->termIds());
        $relationRepository->replace(ResourceType::Settings, $id, ResourceType::Author, $this->event->authorIds());
        $relationRepository->replace(ResourceType::Settings, $id, ResourceType::External, $this->event->externalIds());
    }
}
