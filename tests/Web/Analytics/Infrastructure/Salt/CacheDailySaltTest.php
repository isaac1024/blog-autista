<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Salt;

use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Analytics\Domain\Day;
use Web\Analytics\Infrastructure\Salt\CacheDailySalt;

final class CacheDailySaltTest extends TestCase
{
    use ClockSensitiveTrait;

    /**
     * The salt is made once, under a lock of its day, and kept by its day
     * until five minutes after the day is over in Madrid.
     */
    public function testItKeepsTheSaltOfTheDayUntilShortlyAfterItIsOver(): void
    {
        self::mockTime('2026-11-07 22:00:00 Europe/Madrid');
        $cache = new LifetimeRecordingCache();
        $mutualExclusion = new RecordingMutualExclusion();
        $salt = new CacheDailySalt($cache, $mutualExclusion);

        $first = $salt->forDay(Day::today());

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        self::assertSame($first, $salt->forDay(Day::today()));
        self::assertSame(['web-visit-salt-2026-11-07'], $mutualExclusion->lastHeld());
        self::assertSame(['salt-2026-11-07' => 2 * 3600 + 5 * 60], $cache->lifetimes());
    }

    public function testEachDayHasASaltOfItsOwn(): void
    {
        self::mockTime('2026-11-07 22:00:00 Europe/Madrid');
        $salt = new CacheDailySalt(new LifetimeRecordingCache(), new RecordingMutualExclusion());

        $today = $salt->forDay(Day::today());
        self::mockTime('2026-11-08 10:00:00 Europe/Madrid');

        self::assertNotSame($today, $salt->forDay(Day::today()));
    }

    /**
     * A salt asked for once its day is over still lives a moment.
     */
    public function testASaltAskedForLateLivesAMoment(): void
    {
        self::mockTime('2026-11-08 01:00:00 Europe/Madrid');
        $cache = new LifetimeRecordingCache();

        new CacheDailySalt($cache, new RecordingMutualExclusion())->forDay(Day::fromDate(new \DateTimeImmutable('2026-11-07')));

        self::assertSame(['salt-2026-11-07' => 1], $cache->lifetimes());
    }
}
