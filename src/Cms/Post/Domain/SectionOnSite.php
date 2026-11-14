<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Cms\Shared\Domain\Slug;

/**
 * A section as the site shows it: its name, and the slug its posts are
 * addressed under.
 */
final readonly class SectionOnSite
{
    public function __construct(
        private string $name,
        private Slug $slug,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }
}
