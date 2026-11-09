<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Outbound port that keeps the visits until they are counted, and counts them.
 */
interface VisitRepository
{
    public function save(Visit $visit): void;

    /**
     * The days before the one given that still have visits to count, oldest first.
     *
     * @return list<Day>
     */
    public function daysToCountBefore(Day $day): array;

    /**
     * Counts the visits of the day by every dimension and forgets them.
     */
    public function count(Day $day): void;
}
