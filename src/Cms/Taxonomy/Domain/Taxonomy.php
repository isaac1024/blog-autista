<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Slug;

/**
 * A way of cataloguing the posts of the blog, flat, without terms inside
 * others. Each serves its terms at addresses of its own, so a section and a
 * tag may share a slug, though two terms of one taxonomy never do.
 *
 * Sections are served at the first level of the site, /{slug}/, so a section
 * never takes a slug that names a path the site serves something else under.
 */
enum Taxonomy: string
{
    case Section = 'section';
    case Tag = 'tag';

    /**
     * The first segments of the site that other things are served under: the
     * authors, the tags, the copies of the images and the assets.
     */
    private const array RESERVED_SECTION_SLUGS = ['assets', 'autor', 'media', 'tag'];

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidTaxonomy::unknown($value);
    }

    /**
     * The slug, once checked that a term of the taxonomy may take it.
     */
    public function allows(Slug $slug): Slug
    {
        if (self::Section === $this && \in_array($slug->value(), self::RESERVED_SECTION_SLUGS, true)) {
            throw InvalidTermSlug::reserved($slug);
        }

        return $slug;
    }

    /**
     * The slug a term takes from what an editor writes, blank for none: a
     * term may be left without one until it is published.
     */
    public function slugFrom(string $slug): ?Slug
    {
        return '' === $slug ? null : $this->allows(new Slug($slug));
    }
}
