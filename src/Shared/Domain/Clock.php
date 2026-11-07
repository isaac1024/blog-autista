<?php

declare(strict_types=1);

namespace Shared\Domain;

use Symfony\Component\Clock\Clock as SymfonyClock;

/**
 * Tells the domain the current time, so it dates what happens itself.
 *
 * It reads the global Symfony clock, which tests freeze with
 * ClockSensitiveTrait::mockTime().
 */
final class Clock
{
    public static function now(): \DateTimeImmutable
    {
        return SymfonyClock::get()->now();
    }
}
