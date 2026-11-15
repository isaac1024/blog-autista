<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Usage;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceUsage;
use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * an external resource.
 */
#[AsAlias(ExternalResourceUsage::class)]
final readonly class RelationExternalResourceUsage implements ExternalResourceUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(ExternalResourceId $id): bool
    {
        return new UsageChecker(ResourceType::External, $id->value())($this->relationRepository);
    }
}
