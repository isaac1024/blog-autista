<?php

declare(strict_types=1);

namespace Web\Shared\Domain;

use Doctrine\ORM\Mapping as ORM;

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
        // Rejects what is no id of any aggregate.
        new AggregateId($aggregateId);

        if ('' === $projection || $revision < 0) {
            throw new \InvalidArgumentException('The projection checkpoint is invalid.');
        }
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
