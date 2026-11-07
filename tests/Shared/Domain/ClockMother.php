<?php

declare(strict_types=1);

namespace Shared\Tests\Domain;

/**
 * The known time tests freeze the clock at, with ClockSensitiveTrait, so what
 * they build is dated as the code under test dates it.
 */
final class ClockMother
{
    public const string NOW = '2026-11-07 10:00:00';

    public static function now(string $now = self::NOW): \DateTimeImmutable
    {
        return new \DateTimeImmutable($now);
    }
}
