<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;

/**
 * Tells whether a resource of the CMS is in use: whether any other uses it.
 */
final readonly class UsageChecker
{
    public function __construct(
        private ResourceType $type,
        private string $id,
    ) {}

    public function __invoke(RelationRepository $relationRepository): bool
    {
        return $relationRepository->isUsed($this->type, $this->id);
    }
}
