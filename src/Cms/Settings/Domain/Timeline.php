<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * When the settings were created, last changed, and first and last published.
 *
 * - The update date only moves when what an editor writes changes.
 * - The first publication date never moves once set.
 * - The last publication date is when the site last changed.
 */
#[ORM\Embeddable]
final readonly class Timeline
{
    public function __construct(
        #[ORM\Column(name: 'created_at')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at')]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'first_published_at', nullable: true)]
        private ?\DateTimeImmutable $firstPublishedAt,
        #[ORM\Column(name: 'last_published_at', nullable: true)]
        private ?\DateTimeImmutable $lastPublishedAt,
    ) {
        if ((null === $firstPublishedAt) !== (null === $lastPublishedAt)) {
            throw InvalidSettingsPublication::incompleteDates();
        }
    }

    public static function start(\DateTimeImmutable $now): self
    {
        return new self($now, $now, null, null);
    }

    public function updated(\DateTimeImmutable $now): self
    {
        return new self($this->createdAt, $now, $this->firstPublishedAt, $this->lastPublishedAt);
    }

    public function published(\DateTimeImmutable $now): self
    {
        return new self($this->createdAt, $this->updatedAt, $this->firstPublishedAt ?? $now, $now);
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
