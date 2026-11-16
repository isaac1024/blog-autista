<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

final class SitemapFileNotFound extends \RuntimeException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The sitemap has no file "%s".', $name));
    }
}
