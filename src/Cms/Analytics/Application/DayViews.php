<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

/**
 * The pages seen on a day of the period.
 */
final readonly class DayViews
{
    public function __construct(
        public string $day,
        public int $views,
    ) {}
}
