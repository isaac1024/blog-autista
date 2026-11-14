<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Application;

use Cms\Analytics\Application\DayViews;
use Cms\Analytics\Application\ReportRow;
use Cms\Analytics\Application\VisitReport;
use Cms\Analytics\Application\VisitReportFinder;
use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;
use Cms\Tests\Analytics\Domain\CountMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The report of what the site counted over a period ending yesterday, day
 * by day, and of today so far, and whether the nightly count is late. It is
 * 2026-11-09 in Madrid, so yesterday is 2026-11-08.
 */
final class VisitReportFinderTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string NOW = '2026-11-09 10:00:00 Europe/Madrid';
    private const string PAGE = CountMother::PAGE;
    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105f002';
    private const string DELETED_PAGE = '01a1b801-ce58-723d-8234-b76d9105f003';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(self::NOW);
    }

    /**
     * It reads the period ending yesterday, and today apart.
     */
    public function testItReadsThePeriodEndingYesterdayAndTodayApart(): void
    {
        $asked = [];
        $visitCounts = $this->createMock(VisitCounts::class);
        $visitCounts->expects(self::exactly(2))->method('during')->willReturnCallback(static function (Days $days) use (&$asked): array {
            $asked[] = [$days->first(), $days->last()];

            return [];
        });
        $visitCounts->method('oldestUncountedDay')->willReturn(null);

        new VisitReportFinder(Period::Week->days())($visitCounts, self::titles());

        self::assertSame([['2026-11-02', '2026-11-08'], ['2026-11-09', '2026-11-09']], $asked);
    }

    public function testItAddsUpTheDaysOfThePeriod(): void
    {
        $report = self::report([
            CountMother::create(day: '2026-11-02', views: 10, visitors: 8),
            CountMother::create(day: '2026-11-05', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Page, value: self::PAGE, views: 6, visitors: 5),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Page, value: self::PAGE, views: 2, visitors: 2),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Page, value: self::DELETED_PAGE, views: 4, visitors: 3),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Device, value: 'mobile', views: 7, visitors: 6),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Device, value: 'mobile', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Device, value: 'desktop', views: 3, visitors: 2),
        ]);

        self::assertSame([12, 10], [$report->period->views, $report->period->visitors]);
        self::assertEquals([new ReportRow(self::PAGE, 'Sobre mí', 'page', 8, 7), new ReportRow(self::DELETED_PAGE, null, 'page', 4, 3)], $report->period->contents);
        self::assertEquals([new ReportRow('mobile', null, null, 9, 8), new ReportRow('desktop', null, null, 3, 2)], $report->period->devices);
    }

    /**
     * The channels count entries to the site: those from within it are left
     * out.
     */
    public function testItLeavesOutTheVisitsFromWithinTheSiteOfTheChannels(): void
    {
        $report = self::report([
            CountMother::create(dimension: Dimension::Channel, value: 'search', views: 5, visitors: 4),
            CountMother::create(dimension: Dimension::Channel, value: 'internal', views: 3, visitors: 2),
            CountMother::create(dimension: Dimension::Channel, value: 'direct', views: 2, visitors: 2),
        ]);

        self::assertEquals([new ReportRow('search', null, null, 5, 4), new ReportRow('direct', null, null, 2, 2)], $report->period->channels);
    }

    /**
     * @param list<Count>     $counts
     * @param list<ReportRow> $sources
     */
    #[DataProvider('sources')]
    public function testItGroupsTheSourcesTooSmallToShowApart(array $counts, array $sources): void
    {
        self::assertSame(self::rows($sources), self::rows(self::report($counts)->period->sources));
    }

    /**
     * The cut is three entries over the days read.
     *
     * @return iterable<string, array{list<Count>, list<ReportRow>}>
     */
    public static function sources(): iterable
    {
        $source = static fn (string $value, int $views, int $visitors, string $day = CountMother::DAY): Count => CountMother::create(day: $day, dimension: Dimension::Source, value: $value, views: $views, visitors: $visitors);

        yield 'some too small' => [
            [$source('google.es', 4, 3), $source('google.es', 2, 2, '2026-11-05'), $source('foro.example.org', 3, 3), $source('blog.example.org', 2, 1), $source('otro.example.org', 1, 1)],
            [new ReportRow('google.es', null, null, 6, 5), new ReportRow('foro.example.org', null, null, 3, 3), new ReportRow(null, null, null, 3, 2)],
        ];
        yield 'none too small' => [
            [$source('google.es', 5, 4), $source('foro.example.org', 3, 3)],
            [new ReportRow('google.es', null, null, 5, 4), new ReportRow('foro.example.org', null, null, 3, 3)],
        ];
        yield 'all too small' => [
            [$source('blog.example.org', 2, 1), $source('otro.example.org', 1, 1)],
            [new ReportRow(null, null, null, 3, 2)],
        ];
    }

    public function testItTellsAnUnknownCountryApart(): void
    {
        $report = self::report([
            CountMother::create(dimension: Dimension::Country, value: 'ES', views: 6, visitors: 5),
            CountMother::create(dimension: Dimension::Country, value: '', views: 4, visitors: 3),
            CountMother::create(dimension: Dimension::Country, value: 'MX', views: 2, visitors: 2),
        ]);

        self::assertSame(self::rows([new ReportRow('ES', null, null, 6, 5), new ReportRow(null, null, null, 4, 3), new ReportRow('MX', null, null, 2, 2)]), self::rows($report->period->countries));
    }

    /**
     * Among the equally seen, the values come in their order, digits kept
     * as text.
     */
    public function testItOrdersTheEquallySeenByTheirValue(): void
    {
        $report = self::report([
            CountMother::create(dimension: Dimension::Source, value: 'zeta.example.org', views: 3, visitors: 3),
            CountMother::create(dimension: Dimension::Source, value: '123', views: 3, visitors: 3),
            CountMother::create(dimension: Dimension::Source, value: 'alfa.example.org', views: 3, visitors: 3),
        ]);

        self::assertSame(['123', 'alfa.example.org', 'zeta.example.org'], array_map(static fn (ReportRow $row): ?string => $row->value, $report->period->sources));
    }

    public function testItGivesEveryDayOfThePeriodEvenWithNoVisit(): void
    {
        $report = self::report([
            CountMother::create(day: '2026-11-02', views: 10),
            CountMother::create(day: '2026-11-05', views: 2),
            // Two counts of one day add up.
            CountMother::create(day: '2026-11-05', views: 1),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Page, value: self::PAGE, views: 2),
        ]);

        self::assertEquals([
            new DayViews('2026-11-02', 10),
            new DayViews('2026-11-03', 0),
            new DayViews('2026-11-04', 0),
            new DayViews('2026-11-05', 3),
            new DayViews('2026-11-06', 0),
            new DayViews('2026-11-07', 0),
            new DayViews('2026-11-08', 0),
        ], $report->days);
    }

    public function testItCountsTodayApart(): void
    {
        $report = self::report([CountMother::create(views: 10, visitors: 8)], today: [
            CountMother::create(day: '2026-11-09'),
            CountMother::create(day: '2026-11-09', dimension: Dimension::Page, value: self::OTHER_PAGE),
        ]);

        self::assertSame([1, 1], [$report->today->views, $report->today->visitors]);
        self::assertEquals([new ReportRow(self::OTHER_PAGE, 'Otra página', 'page', 1, 1)], $report->today->contents);
    }

    public function testItReportsNothingWhenNothingWasCounted(): void
    {
        $report = self::report([]);

        self::assertSame([0, 0, [], [], [], [], []], [$report->period->views, $report->period->visitors, $report->period->contents, $report->period->channels, $report->period->sources, $report->period->devices, $report->period->countries]);
        self::assertCount(7, $report->days);
        self::assertNull($report->uncountedSince);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function uncountedDays(): iterable
    {
        yield 'none' => [self::NOW, null, null];
        yield 'a day before yesterday' => [self::NOW, '2026-11-05', '2026-11-05'];
        yield 'yesterday, before the count is done' => ['2026-11-09 02:59:59 Europe/Madrid', '2026-11-08', null];
        yield 'yesterday, once it is done' => ['2026-11-09 03:00:00 Europe/Madrid', '2026-11-08', '2026-11-08'];
        yield 'yesterday, once it is done, told in universal time' => ['2026-11-09 02:00:00 UTC', '2026-11-08', '2026-11-08'];
        yield 'today, which is never late' => ['2026-11-09 23:59:59 Europe/Madrid', '2026-11-09', null];
    }

    /**
     * A day still kept uncounted is late once the nightly count should have
     * counted it.
     */
    #[DataProvider('uncountedDays')]
    public function testItTellsTheOldestDayTheNightlyCountMissed(string $now, ?string $oldest, ?string $late): void
    {
        self::mockTime($now);

        self::assertSame($late, self::report([], uncounted: $oldest)->uncountedSince);
    }

    public function testThePeriodsLookAsFarBackAsTheySay(): void
    {
        foreach (Period::cases() as $period) {
            $days = self::report([], period: $period)->days;

            self::assertSame([$period->days(), '2026-11-08'], [\count($days), $days[\count($days) - 1]->day]);
        }
    }

    /**
     * The days are Madrid's: at 23:30 in universal time, it is already the
     * next day there, and yesterday with it.
     */
    public function testItTellsTheDaysAsMadridDoes(): void
    {
        self::mockTime('2026-11-08 23:30:00 UTC');

        $days = self::report([])->days;

        self::assertSame('2026-11-08', $days[\count($days) - 1]->day);
    }

    public function testItRefusesAPeriodTheReportDoesNotCover(): void
    {
        $visitCounts = $this->createMock(VisitCounts::class);
        $visitCounts->expects(self::never())->method('during');

        self::assertTells('errors.analytics.unknown_period', ['%days%' => 14], static fn () => new VisitReportFinder(14)($visitCounts, self::titles()));
    }

    /**
     * @param list<Count> $counts those of the period
     * @param list<Count> $today  those of today
     */
    private static function report(array $counts, array $today = [], ?string $uncounted = null, Period $period = Period::Week): VisitReport
    {
        $visitCounts = self::createStub(VisitCounts::class);
        // Today is the only run of days that ends where it begins.
        $visitCounts->method('during')->willReturnCallback(static fn (Days $days): array => $days->first() === $days->last() ? $today : $counts);
        $visitCounts->method('oldestUncountedDay')->willReturn($uncounted);

        return new VisitReportFinder($period->days())($visitCounts, self::titles());
    }

    /**
     * Rows as plain values, so a null value is told from an empty one.
     *
     * @param list<ReportRow> $rows
     *
     * @return list<array{?string, ?string, int, int}>
     */
    private static function rows(array $rows): array
    {
        return array_map(static fn (ReportRow $row): array => [$row->value, $row->name, $row->views, $row->visitors], $rows);
    }

    /**
     * The titles of the pages still on the CMS; the deleted one has none.
     */
    private static function titles(): ContentNames
    {
        $titles = self::createStub(ContentNames::class);
        $titles->method('namesOf')->willReturnCallback(static fn (Dimension $kind, array $ids): array => Dimension::Page !== $kind ? [] : array_filter(
            [self::PAGE => ['name' => 'Sobre mí', 'kind' => 'page'], self::OTHER_PAGE => ['name' => 'Otra página', 'kind' => 'page']],
            static fn (string $id): bool => \in_array($id, $ids, true),
            \ARRAY_FILTER_USE_KEY,
        ));

        return $titles;
    }
}
