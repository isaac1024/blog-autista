<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

/**
 * Whether search engines may index what asks them so: only what is to be
 * indexed goes in the sitemap.
 */
final class Indexable
{
    public static function robots(string $robots): bool
    {
        return str_starts_with($robots, 'index,');
    }
}
