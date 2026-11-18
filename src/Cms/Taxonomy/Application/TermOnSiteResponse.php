<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Term;

/**
 * A term as the site shows it: its name, the slug it is addressed by, and
 * its taxonomy, which tells a section from a tag.
 */
final readonly class TermOnSiteResponse
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $metaDescription,
        public string $taxonomy,
    ) {}

    public function isSection(): bool
    {
        return TermResponse::SECTION === $this->taxonomy;
    }

    /**
     * The term as it is on the site, none while it is off it.
     */
    public static function fromTerm(Term $term): ?self
    {
        $version = $term->onSite();

        if (null === $version) {
            return null;
        }

        $slug = $version->slug();

        return null === $slug
            ? null
            : new self($version->name()->value(), $slug->value(), $version->seo()->description(), $term->taxonomy()->value);
    }
}
