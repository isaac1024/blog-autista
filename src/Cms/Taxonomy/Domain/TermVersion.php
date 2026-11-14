<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;

/**
 * Everything an editor writes on a term: its name, its address and how it
 * presents itself to search engines. A term holds the version being edited
 * and, once published, the version on the site.
 */
#[ORM\Embeddable]
final readonly class TermVersion
{
    public function __construct(
        #[ORM\Embedded(class: TermName::class, columnPrefix: false)]
        private TermName $name,
        #[ORM\Column(name: 'slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
        private ?Slug $slug,
        #[ORM\Embedded(class: Seo::class, columnPrefix: false)]
        private Seo $seo,
    ) {}

    /**
     * Whether it has everything the site shows of a term: a slug to be
     * addressed by, and a meta description to summarise it in search
     * results. The name and the robots directive always have a value.
     */
    public function assertPublishable(): void
    {
        if (null === $this->slug) {
            throw InvalidTermPublication::withoutSlug();
        }

        if (null === $this->seo->description()) {
            throw InvalidTermPublication::withoutMetaDescription();
        }
    }

    public function equals(self $other): bool
    {
        return $this->name->value() === $other->name->value()
            && $this->slug?->value() === $other->slug?->value()
            && $this->seo->description() === $other->seo->description()
            && $this->seo->robots() === $other->seo->robots();
    }

    public function name(): TermName
    {
        return $this->name;
    }

    /**
     * Null while the term is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->slug;
    }

    public function seo(): Seo
    {
        return $this->seo;
    }
}
