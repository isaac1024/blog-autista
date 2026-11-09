<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * When a redirect was created and last changed.
 */
#[ORM\Embeddable]
final readonly class Timeline
{
    public function __construct(
        #[ORM\Column(name: 'created_at')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at')]
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function start(\DateTimeImmutable $now): self
    {
        return new self($now, $now);
    }

    public function updated(\DateTimeImmutable $now): self
    {
        return new self($this->createdAt, $now);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
