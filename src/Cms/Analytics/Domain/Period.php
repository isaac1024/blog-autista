<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * How many days back the report looks, up to yesterday.
 */
enum Period: int
{
    case Week = 7;
    case Month = 30;
    case Quarter = 90;

    /**
     * @throws InvalidPeriod when the report covers no period of those days
     */
    public static function fromDays(int $days): self
    {
        return self::tryFrom($days) ?? throw InvalidPeriod::ofDays($days);
    }

    public function days(): int
    {
        return $this->value;
    }
}
