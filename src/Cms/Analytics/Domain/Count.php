<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * How many pages were seen on a day, and by how many readers of that day,
 * by one value of a dimension. An unknown country has an empty value.
 */
final readonly class Count
{
    public function __construct(
        public string $day,
        public Dimension $dimension,
        public string $value,
        public int $views,
        public int $visitors,
    ) {}
}
