<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

/**
 * The visits to the site over a period ending yesterday, day by day, and
 * those of today so far; and, when the nightly count is late, the oldest day
 * it left uncounted, whose readers the site still tells apart.
 */
final readonly class VisitReport
{
    /**
     * @param list<DayViews> $days every day of the period, oldest first, those with no visit included
     */
    public function __construct(
        public VisitSummary $period,
        public array $days,
        public VisitSummary $today,
        public ?string $uncountedSince,
    ) {}
}
