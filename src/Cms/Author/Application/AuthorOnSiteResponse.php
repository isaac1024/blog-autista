<?php

declare(strict_types=1);

namespace Cms\Author\Application;

/**
 * An author as the site shows it: its name and the slug it is addressed by.
 */
final readonly class AuthorOnSiteResponse
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {}
}
