<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

/**
 * An address as a file of the sitemap tells it: where, when it last changed,
 * and the image of a post, where its copies are kept, if any.
 */
final readonly class SitemapLine
{
    public function __construct(
        public string $path,
        public \DateTimeImmutable $lastmod,
        public ?string $imagePath,
    ) {}
}
