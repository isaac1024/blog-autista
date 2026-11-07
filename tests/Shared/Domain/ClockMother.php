<?php

declare(strict_types=1);

namespace Shared\Tests\Domain;

use Shared\Domain\Clock;
use Shared\Infrastructure\Clock\SymfonyClock;
use Symfony\Component\Clock\MockClock;

/**
 * Clocks stopped at a known time, so what tests build is dated as the code
 * under test dates it.
 */
final class ClockMother
{
    public const string NOW = '2026-11-07 10:00:00';

    public static function create(string $now = self::NOW): Clock
    {
        return new SymfonyClock(new MockClock(new \DateTimeImmutable($now)));
    }

    public static function now(string $now = self::NOW): \DateTimeImmutable
    {
        return new \DateTimeImmutable($now);
    }
}
