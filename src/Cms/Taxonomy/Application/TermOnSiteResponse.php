<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

/**
 * A term as the site shows it: its name and the slug it is addressed by.
 */
final readonly class TermOnSiteResponse
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $metaDescription,
    ) {}
}
