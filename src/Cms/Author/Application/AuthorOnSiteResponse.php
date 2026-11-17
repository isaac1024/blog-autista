<?php

declare(strict_types=1);

namespace Cms\Author\Application;

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
}
