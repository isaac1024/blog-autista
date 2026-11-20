<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * A section as the site shows it: its name.
 */
final readonly class SectionOnSite
{
    public function __construct(private string $name) {}

    public function name(): string
    {
        return $this->name;
    }
}
