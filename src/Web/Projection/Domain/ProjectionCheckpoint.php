<?php

declare(strict_types=1);

namespace Web\Projection\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Last revision applied to an aggregate, kept even when its public resource
 * has been removed. The compound id doubles as a uniqueness guard.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_projection_checkpoint')]
final class ProjectionCheckpoint
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 32)]
        private string $projection,
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $aggregateId,
        #[ORM\Column]
        private int $revision,
    ) {
        if ('' === $projection || !Uuid::isValid($aggregateId) || $revision < 0) {
            throw new \InvalidArgumentException('The projection checkpoint is invalid.');
        }
    }

    public static function for(string $projection, string $aggregateId, int $revision): self
    {
        return new self($projection, $aggregateId, $revision);
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function advance(int $revision): void
    {
        $this->revision = $revision;
    }
}
