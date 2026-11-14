<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * A term of a taxonomy on the site, as the CMS last published it: only
 * published terms are here, and each is served at the address of its
 * taxonomy with its slug.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_term')]
#[ORM\UniqueConstraint(name: 'uniq_web_term_slug', columns: ['taxonomy', 'slug'])]
final class Term
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_term_id')]
        private readonly TermId $id,
        #[ORM\Column(length: 20, enumType: Taxonomy::class)]
        private readonly Taxonomy $taxonomy,
        #[ORM\Column(length: 60)]
        private string $name,
        #[ORM\Column(length: 80)]
        private string $slug,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $metaDescription,
        #[ORM\Column(length: 20, enumType: Robots::class)]
        private Robots $robots,
        #[ORM\Column]
        private \DateTimeImmutable $firstPublishedAt,
        #[ORM\Column]
        private \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function publish(CmsTermPublished $event): self
    {
        return new self(
            new TermId($event->aggregateId()),
            $event->taxonomy(),
            $event->name(),
            $event->slug(),
            $event->metaDescription(),
            $event->robots(),
            $event->firstPublishedAt(),
            $event->lastPublishedAt(),
        );
    }

    /**
     * Takes the version the CMS published, in place of the one on the site.
     * A term never changes taxonomy.
     */
    public function republish(CmsTermPublished $event): void
    {
        $this->name = $event->name();
        $this->slug = $event->slug();
        $this->metaDescription = $event->metaDescription();
        $this->robots = $event->robots();
        $this->firstPublishedAt = $event->firstPublishedAt();
        $this->lastPublishedAt = $event->lastPublishedAt();
    }

    /**
     * Takes the term off the site.
     */
    public function unpublish(TermRepository $termRepository): void
    {
        $termRepository->delete($this);
    }

    /**
     * Keeps the term, as long as no other term of its taxonomy on the site is
     * at its address.
     *
     * @throws SlugTaken while the event that frees the address has yet to arrive
     */
    public function save(TermRepository $termRepository): void
    {
        $holder = $termRepository->findBySlug($this->taxonomy, $this->slug);

        if (null !== $holder && !$holder->id->equals($this->id)) {
            throw SlugTaken::withSlug($this->slug);
        }

        $termRepository->save($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }
}
