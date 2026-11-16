<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

/**
 * An address as a file of the sitemap tells it: where, when it last changed,
 * and the image a post opens with, by its id, and where its copies are
 * kept while it is on the site.
 */
final readonly class SitemapLine
{
    public function __construct(
        public string $path,
        public \DateTimeImmutable $lastmod,
        public ?string $imageId,
        public ?string $imagePath,
    ) {}
}
