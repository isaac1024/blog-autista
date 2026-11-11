<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Analytics\Domain\VisitRepository;
use Web\Analytics\Infrastructure\Scheduler\NightlyVisitCount;
use Web\Tests\Analytics\Domain\VisitIdMother;
use Web\Tests\Analytics\Domain\VisitMother;

/**
 * What the count does is told by VisitCounterTest, and how the database
 * counts by DoctrineVisitRepositoryTest; here, that the nightly task counts
 * every day left uncounted, and leaves today alone.
 */
final class NightlyVisitCountTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testItCountsEveryDayLeftUncountedButToday(): void
    {
        self::mockTime('2026-11-07 23:30:00 Europe/Madrid');
        $visits = self::getContainer()->get(VisitRepository::class);

        foreach (['2026-11-05', '2026-11-06', '2026-11-07'] as $day) {
            $visits->save(VisitMother::create(id: VisitIdMother::random()->value(), day: $day));
        }

        self::getContainer()->get(NightlyVisitCount::class)();

        $connection = self::getContainer()->get(Connection::class);
        self::assertSame(['2026-11-05', '2026-11-06'], $connection->fetchFirstColumn("SELECT day FROM web_visit_total WHERE dimension = 'site' ORDER BY day"));
        self::assertSame(['2026-11-07'], $connection->fetchFirstColumn('SELECT day FROM web_visit'));
    }
}
