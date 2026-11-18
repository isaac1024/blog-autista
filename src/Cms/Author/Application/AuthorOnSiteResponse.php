<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\Author;

/**
 * An author as the site shows it: its name, the slug it is addressed by,
 * its avatar, where the CMS keeps its file, null for none, and what it says
 * of itself to search engines.
 */
final readonly class AuthorOnSiteResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $avatar,
        public ?string $metaDescription,
    ) {}

    /**
     * The author as it is on the site, none while it is off it.
     */
    public static function fromAuthor(Author $author): ?self
    {
        $version = $author->onSite();

        if (null === $version) {
            return null;
        }

        $slug = $version->slug();

        return null === $slug
            ? null
            : new self($author->id()->value(), $version->name()->value(), $slug->value(), $version->avatar()?->value(), $version->seo()->description());
    }
}
