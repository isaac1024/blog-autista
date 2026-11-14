<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Usage;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorUsage;
use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * an author.
 */
#[AsAlias(AuthorUsage::class)]
final readonly class RelationAuthorUsage implements AuthorUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(AuthorId $id): bool
    {
        return new UsageChecker(ResourceType::Author, $id->value())($this->relationRepository);
    }
}
