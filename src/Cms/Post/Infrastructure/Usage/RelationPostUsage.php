<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Usage;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostUsage;
use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * a post.
 */
#[AsAlias(PostUsage::class)]
final readonly class RelationPostUsage implements PostUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(PostId $id): bool
    {
        return new UsageChecker(ResourceType::Post, $id->value())($this->relationRepository);
    }
}
