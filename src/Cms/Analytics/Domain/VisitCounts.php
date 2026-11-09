<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * Outbound port that reads what the site counted of its visits: the days it
 * already counted, and those it still keeps the visits of, counted as read.
 */
interface VisitCounts
{
    /**
     * @return list<Count>
     */
    public function during(Days $days): array;
}
