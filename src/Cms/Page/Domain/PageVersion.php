<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * Everything an editor writes on a page: its title, address, content and how
 * it presents itself to search engines. A page holds the version being
 * edited and, once published, the version on the site.
 */
#[ORM\Embeddable]
final readonly class PageVersion
{
    public function __construct(
        #[ORM\Embedded(class: PageTitle::class, columnPrefix: false)]
        private PageTitle $title,
        #[ORM\Column(name: 'slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
        private ?Slug $slug,
        #[ORM\Column(name: 'content', type: 'rich_text')]
        private RichText $content,
        #[ORM\Embedded(class: Seo::class, columnPrefix: false)]
        private Seo $seo,
    ) {}

    /**
     * Whether it has everything the site shows of a page of the type: a slug
     * to be addressed by, unless it is the home page, content, and a meta
     * description to summarise it in search results and shared links. The
     * title and the robots directive always have a value.
     */
    public function assertPublishableAs(PageType $type): void
    {
        if (null === $this->slug && !$type->isHome()) {
            throw InvalidPagePublication::withoutSlug();
        }

        if ([] === $this->content->blocks()) {
            throw InvalidPagePublication::withoutContent();
        }

        if (null === $this->seo->description()) {
            throw InvalidPagePublication::withoutMetaDescription();
        }
    }

    public function equals(self $other): bool
    {
        return $this->title->value() === $other->title->value()
            && $this->slug?->value() === $other->slug?->value()
            && $this->content->toArray() === $other->content->toArray()
            && $this->seo->description() === $other->seo->description()
            && $this->seo->robots() === $other->seo->robots();
    }

    public function title(): PageTitle
    {
        return $this->title;
    }

    /**
     * Null for the home page, and for any other page left without one.
     */
    public function slug(): ?Slug
    {
        return $this->slug;
    }

    public function content(): RichText
    {
        return $this->content;
    }

    public function seo(): Seo
    {
        return $this->seo;
    }
}
