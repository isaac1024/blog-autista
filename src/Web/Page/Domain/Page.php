<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * A page on the site, as the CMS last published it: only published pages
 * are here, ready to be served.
 *
 * Events reach the site through a queue, which may deliver one twice or an
 * old one after a newer one. So a page remembers when the last event it took
 * happened, and takes no older one.
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
        #[ORM\Column]
        private \DateTimeImmutable $lastEventOn,
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
            $event->occurredOn(),
        );
    }

    /**
     * Takes the version the CMS published, unless the page already took a
     * later event.
     */
    public function republish(CmsPagePublished $event): void
    {
        if ($this->tookLaterThan($event->occurredOn())) {
            return;
        }

        $this->type = $event->type();
        $this->title = $event->title();
        $this->slug = $event->slug();
        $this->content = $event->content();
        $this->metaDescription = $event->metaDescription();
        $this->robots = $event->robots();
        $this->firstPublishedAt = $event->firstPublishedAt();
        $this->lastPublishedAt = $event->lastPublishedAt();
        $this->lastEventOn = $event->occurredOn();
    }

    /**
     * Takes the page off the site, unless it was published again after the
     * CMS took it off.
     */
    public function unpublish(CmsPageUnpublished $event, PageRepository $pageRepository): void
    {
        if ($this->tookLaterThan($event->occurredOn())) {
            return;
        }

        $pageRepository->delete($this);
    }

    public function save(PageRepository $pageRepository): void
    {
        $pageRepository->save($this);
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

    private function tookLaterThan(\DateTimeImmutable $occurredOn): bool
    {
        return $this->lastEventOn > $occurredOn;
    }
}
