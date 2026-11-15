<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;
use Web\Shared\Domain\Robots;

/**
 * A page on the site, as the CMS last published it: only published pages
 * are here, ready to be served.
 *
 * Events reach the site through a queue, which may deliver one twice or an
 * old one after a newer one: the projection that hands them over only lets
 * through those newer than the last it applied, by their revision.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_page')]
#[ORM\UniqueConstraint(name: 'uniq_web_page_slug', columns: ['slug'])]
final class Page
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_page_id')]
        private readonly PageId $id,
        #[ORM\Column(length: 20, enumType: PageType::class)]
        private PageType $type,
        #[ORM\Column(length: 100)]
        private string $title,
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $slug,
        #[ORM\Column(type: 'rich_text')]
        private RichText $content,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $metaDescription,
        #[ORM\Column(length: 20, enumType: Robots::class)]
        private Robots $robots,
        #[ORM\Column]
        private \DateTimeImmutable $firstPublishedAt,
        #[ORM\Column]
        private \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function publish(CmsPagePublished $event): self
    {
        return new self(
            new PageId($event->aggregateId()),
            $event->type(),
            $event->title(),
            $event->slug(),
            $event->content(),
            $event->metaDescription(),
            $event->robots(),
            $event->firstPublishedAt(),
            $event->lastPublishedAt(),
        );
    }

    /**
     * Takes the version the CMS published.
     */
    public function republish(CmsPagePublished $event): void
    {
        $this->type = $event->type();
        $this->title = $event->title();
        $this->slug = $event->slug();
        $this->content = $event->content();
        $this->metaDescription = $event->metaDescription();
        $this->robots = $event->robots();
        $this->firstPublishedAt = $event->firstPublishedAt();
        $this->lastPublishedAt = $event->lastPublishedAt();
    }

    /**
     * Takes the page off the site.
     */
    public function unpublish(PageRepository $pageRepository): void
    {
        $pageRepository->delete($this);
    }

    /**
     * Keeps the page, as long as no other page on the site is at its address.
     *
     * @throws SlugTaken while the event that frees the address has yet to arrive
     */
    public function save(PageRepository $pageRepository): void
    {
        if (null !== $this->slug) {
            $holder = $pageRepository->findBySlug($this->slug);

            if (null !== $holder && !$holder->id->equals($this->id)) {
                throw SlugTaken::withSlug($this->slug);
            }
        }

        $pageRepository->save($this);
    }

    public function id(): PageId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * Null for the home page.
     */
    public function slug(): ?string
    {
        return $this->slug;
    }

    public function content(): RichText
    {
        return $this->content;
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
