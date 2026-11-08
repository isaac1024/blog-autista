<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Usage;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageUsage;
use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the relations of the CMS whether anything uses
 * an image.
 */
#[AsAlias(ImageUsage::class)]
final readonly class RelationImageUsage implements ImageUsage
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public function isInUse(ImageId $id): bool
    {
        return new UsageChecker(ResourceType::Image, $id->value())($this->relationRepository);
    }
}
