<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Usage;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageUsage;
use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * a page.
 */
#[AsAlias(PageUsage::class)]
final readonly class RelationPageUsage implements PageUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(PageId $id): bool
    {
        return new UsageChecker(ResourceType::Page, $id->value())($this->relationRepository);
    }
}
