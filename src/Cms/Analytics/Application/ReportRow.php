<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

/**
 * One line of a breakdown: what it counts, such as a page id, a channel or
 * a country code, with a name when it has one, what kind of content it is,
 * when it is one, and its counts. A null value groups those too small to
 * show apart, or a country unknown.
 */
final readonly class ReportRow
{
    /**
     * @param ?string $kind what the content is, such as "page", "post", "section", "tag" or "author"; that it is counted by, such as "term", when it was deleted; null for what isn't content
     */
    public function __construct(
        public ?string $value,
        public ?string $name,
        public ?string $kind,
        public int $views,
        public int $visitors,
    ) {}
}
