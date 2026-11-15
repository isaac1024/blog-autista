<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPostExternalsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a post to the external resources it links to
 * with those it tells.
 */
final readonly class PostExternalsRelator
{
    public function __construct(private CmsPostExternalsLinked $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Post, $this->event->aggregateId(), ResourceType::External, $this->event->externalIds());
    }
}
