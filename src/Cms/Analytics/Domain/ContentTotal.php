<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * What a page of the site, a post, a section or a tag, or an author adds up
 * to over some days: the kind it is counted by, its id, the times it was
 * seen, and its readers of each day, summed.
 */
final readonly class ContentTotal
{
    public function __construct(
        public Dimension $kind,
        public string $id,
        public int $views,
        public int $visitors,
    ) {}
}
