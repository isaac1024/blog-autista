<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Infrastructure\Persistence;

use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;
use Cms\Tests\Analytics\Domain\CountMother;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The counts of the days the site already counted, and, for the days it
 * still keeps the visits of, the same counts made as they are read. It is
 * 2026-11-09 in Madrid, so a week ends on 2026-11-08 and begins on
 * 2026-11-02.
 */
final class DoctrineVisitCountsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105f002';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-09 10:00:00 Europe/Madrid');
    }

    /**
     * The CMS reads the tables the site counts the visits in, the one pair
     * of tables of the site it reads: the columns it reads are those the
     * site keeps, so a migration of the site that renames or drops one
     * breaks the report here, not in production.
     *
     * @param list<string> $read
     */
    #[DataProvider('tablesRead')]
    public function testTheTablesItReadsHaveTheColumnsItReads(string $table, array $read): void
    {
        $columns = $this->connection()->fetchFirstColumn(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table],
        );

        foreach ($read as $column) {
            self::assertContains($column, $columns);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function tablesRead(): iterable
    {
        yield 'the counts of the days counted' => ['shared_visit_total', ['day', 'dimension', 'value', 'views', 'visitors']];
        yield 'the visits of the days not counted yet' => ['shared_visit', ['day', 'content_type', 'content_id', 'channel', 'source', 'device', 'country', 'reader']];
    }

    public function testItReadsTheCountsOfTheDaysAlreadyCountedWithinThem(): void
    {
        $this->counted('2026-11-01', 'site', '', 100, 100);
        $this->counted('2026-11-02', 'site', '', 10, 8);
        $this->counted('2026-11-08', 'page', CountMother::PAGE, 6, 5);
        $this->counted('2026-11-08', 'unknown', 'x', 1, 1);

        self::assertEquals([
            CountMother::create(day: '2026-11-02', views: 10, visitors: 8),
            CountMother::create(day: '2026-11-08', dimension: Dimension::Page, value: CountMother::PAGE, views: 6, visitors: 5),
        ], self::sorted($this->visitCounts()->during(Days::endingYesterday(Period::Week))));
    }

    /**
     * A day still kept is counted as read, by every dimension, each counting
     * its own readers. A source counts only readers who entered through it,
     * never those coming from within the site, and an unknown country
     * counts under no value.
     */
    public function testItCountsTheVisitsStillKeptAsItReadsThem(): void
    {
        $this->kept('01a1b801-ce58-723d-8234-b76d9105f101', CountMother::PAGE, 'search', 'google.es', 'a', 'mobile', 'MX');
        $this->kept('01a1b801-ce58-723d-8234-b76d9105f102', CountMother::PAGE, 'search', 'google.es', 'b', 'mobile', 'MX');
        $this->kept('01a1b801-ce58-723d-8234-b76d9105f103', self::OTHER_PAGE, 'internal', 'blogautista.localhost', 'a', 'mobile', null);
        $this->kept('01a1b801-ce58-723d-8234-b76d9105f104', self::OTHER_PAGE, 'direct', null, 'c', 'desktop', null, day: '2026-11-09');

        self::assertEquals(self::sorted([
            CountMother::create(day: '2026-11-05', views: 3, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Page, value: CountMother::PAGE, views: 2, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Page, value: self::OTHER_PAGE, views: 1, visitors: 1),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Channel, value: 'search', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Channel, value: 'internal', views: 1, visitors: 1),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Source, value: 'google.es', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Device, value: 'mobile', views: 3, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Country, value: 'MX', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Country, value: '', views: 1, visitors: 1),
        ]), self::sorted($this->visitCounts()->during(Days::endingYesterday(Period::Week))));
        self::assertEquals(self::sorted([
            CountMother::create(day: '2026-11-09'),
            CountMother::create(day: '2026-11-09', dimension: Dimension::Page, value: self::OTHER_PAGE),
            CountMother::create(day: '2026-11-09', dimension: Dimension::Channel, value: 'direct'),
            CountMother::create(day: '2026-11-09', dimension: Dimension::Device, value: 'desktop'),
            CountMother::create(day: '2026-11-09', dimension: Dimension::Country),
        ]), self::sorted($this->visitCounts()->during(Days::today())));
    }

    public function testItTellsTheOldestDayItStillKeepsTheVisitsOf(): void
    {
        self::assertNull($this->visitCounts()->oldestUncountedDay());

        $this->kept('01a1b801-ce58-723d-8234-b76d9105f101', CountMother::PAGE, 'direct', null, 'a', 'desktop', null, day: '2026-11-07');
        $this->kept('01a1b801-ce58-723d-8234-b76d9105f102', CountMother::PAGE, 'direct', null, 'a', 'desktop', null, day: '2026-11-05');

        self::assertSame('2026-11-05', $this->visitCounts()->oldestUncountedDay());
    }

    private function counted(string $day, string $dimension, string $value, int $views, int $visitors): void
    {
        $this->connection()->insert('shared_visit_total', ['day' => $day, 'dimension' => $dimension, 'value' => $value, 'views' => $views, 'visitors' => $visitors]);
    }

    private function kept(string $id, string $page, string $channel, ?string $source, string $reader, string $device, ?string $country, string $day = '2026-11-05'): void
    {
        $this->connection()->insert('shared_visit', [
            'id' => $id, 'content_type' => 'page', 'content_id' => $page, 'day' => $day, 'channel' => $channel, 'source' => $source,
            'reader' => str_repeat($reader, 64), 'device' => $device, 'country' => $country,
        ]);
    }

    /**
     * @param list<Count> $counts
     *
     * @return list<Count>
     */
    private static function sorted(array $counts): array
    {
        usort($counts, static fn (Count $one, Count $other): int => [$one->day, $one->dimension->value, $one->value] <=> [$other->day, $other->dimension->value, $other->value]);

        return $counts;
    }

    private function visitCounts(): VisitCounts
    {
        return self::getContainer()->get(VisitCounts::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
