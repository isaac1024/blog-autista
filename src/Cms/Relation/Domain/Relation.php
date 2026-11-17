<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * One resource of the CMS using another, such as a page embedding an image:
 * while it does, the one used is in use.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_relation')]
#[ORM\UniqueConstraint(name: 'uniq_cms_relation', columns: ['source_type', 'source_id', 'target_type', 'target_id'])]
#[ORM\Index(name: 'idx_cms_relation_target', columns: ['target_type', 'target_id'])]
final class Relation
{
    /**
     * @param string $sourceId id of the resource that uses the other
     * @param string $targetId id of the resource used
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_relation_id')]
        private readonly RelationId $id,
        #[ORM\Column(length: 20, enumType: ResourceType::class)]
        private readonly ResourceType $sourceType,
        #[ORM\Column(length: 36)]
        private readonly string $sourceId,
        #[ORM\Column(length: 20, enumType: ResourceType::class)]
        private readonly ResourceType $targetType,
        #[ORM\Column(length: 36)]
        private readonly string $targetId,
    ) {}

    public static function between(ResourceType $sourceType, string $sourceId, ResourceType $targetType, string $targetId): self
    {
        return new self(RelationId::random(), $sourceType, $sourceId, $targetType, $targetId);
    }

    public function sourceType(): ResourceType
    {
        return $this->sourceType;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }
}
