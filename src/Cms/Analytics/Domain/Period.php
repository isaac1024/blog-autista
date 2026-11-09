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

    public function days(): int
    {
        return $this->value;
    }
}
