<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

/**
 * One line of a breakdown: what it counts, such as a page id, a channel or
 * a country code, with a name when it has one, and its counts. A null value
 * groups those too small to show apart, or a country unknown.
 */
final readonly class ReportRow
{
    public function __construct(
        public ?string $value,
        public ?string $name,
        public int $views,
        public int $visitors,
    ) {}
}
