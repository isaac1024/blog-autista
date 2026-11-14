<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\CmsPostTermsUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Replaces the relations of a post to the sections and tags it is filed under with those it tells.
 */
final readonly class PostTermsRelator
{
    public function __construct(private CmsPostTermsUsed $event) {}

    public function __invoke(RelationRepository $relationRepository): void
    {
        $relationRepository->replace(ResourceType::Post, $this->event->aggregateId(), ResourceType::Term, $this->event->termIds());
    }
}
