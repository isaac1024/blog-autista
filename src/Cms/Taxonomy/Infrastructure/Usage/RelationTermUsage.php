<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Usage;

use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermUsage;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * a term.
 */
#[AsAlias(TermUsage::class)]
final readonly class RelationTermUsage implements TermUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(TermId $id): bool
    {
        return new UsageChecker(ResourceType::Term, $id->value())($this->relationRepository);
    }
}
