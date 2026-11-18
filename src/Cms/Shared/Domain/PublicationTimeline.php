<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * When something the CMS publishes, such as a page, a post or an image, was
 * created, last changed, and first and last published.
 *
 * - The update date only moves when what an editor writes changes.
 * - The first publication date never moves once set: search engines know a
 *   content by it, and a post carries its day in its address.
 * - The last publication date is when the site last changed, which the site
 *   shows as the last update of a page or a post.
 * - Published once, it has both; never published, neither.
 */
#[ORM\Embeddable]
final readonly class PublicationTimeline
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
            throw InvalidPublicationTimeline::incompleteDates();
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

    public function firstPublishedAt(): ?\DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }
}
