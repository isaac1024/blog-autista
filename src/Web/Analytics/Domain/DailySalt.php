<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Outbound port that keeps a secret per day, the same all day long and gone
 * soon after it, kept away from the visits.
 */
interface DailySalt
{
    public function forDay(Day $day): string;
}
