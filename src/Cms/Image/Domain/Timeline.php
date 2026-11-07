<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * When an image was uploaded and last changed.
 */
#[ORM\Embeddable]
final readonly class Timeline
{
    public function __construct(
        #[ORM\Column(name: 'created_at')]
        private \DateTimeImmutable $createdAt,
        // @phpstan-ignore property.onlyWritten (only Doctrine reads it, to store it)
        #[ORM\Column(name: 'updated_at')]
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function start(\DateTimeImmutable $now): self
    {
        return new self($now, $now);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
