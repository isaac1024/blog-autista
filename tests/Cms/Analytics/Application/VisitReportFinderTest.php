<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Application;

use Cms\Analytics\Application\DayViews;
use Cms\Analytics\Application\ReportRow;
use Cms\Analytics\Application\VisitReport;
use Cms\Analytics\Application\VisitReportFinder;
use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\ContentTotal;
use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\SeenContents;
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
    private const string POST = '01a1b801-ce58-723d-8234-b76d9105f011';
    private const string TAG = '01a1b801-ce58-723d-8234-b76d9105f012';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(self::NOW);
    }

    /**
     * Pages, posts, sections and tags, and authors are reported together, in
     * the order they were counted in, most seen first; each named, and told
     * what it is, as the CMS keeps it; one deleted, by the kind it was
     * counted as.
     */
    public function testItReportsEveryKindOfContentTogether(): void
    {
        $report = self::report([], contents: self::seen(
            new ContentTotal(Dimension::Post, self::POST, 5, 4),
            new ContentTotal(Dimension::Author, self::DELETED_PAGE, 2, 2),
            new ContentTotal(Dimension::Page, self::PAGE, 2, 2),
            new ContentTotal(Dimension::Term, self::TAG, 2, 1),
        ));

        self::assertEquals([
            new ReportRow(self::POST, 'Un post', 'post', 5, 4),
            new ReportRow(self::DELETED_PAGE, null, 'author', 2, 2),
            new ReportRow(self::PAGE, 'Sobre mí', 'page', 2, 2),
            new ReportRow(self::TAG, 'Sueño', 'tag', 2, 1),
        ], $report->period->contents);
        self::assertSame(0, $report->period->otherContents);
    }

    /**
     * The CMS is asked the names of each kind once, by the ids of that kind
     * alone, and never of a kind none was seen of.
     */
    public function testItAsksTheNamesOfEachKindByItsOwnIds(): void
    {
        $visitCounts = self::createStub(VisitCounts::class);
        $visitCounts->method('during')->willReturn([]);
        $visitCounts->method('contentsDuring')->willReturnCallback(static fn (Days $days): SeenContents => $days->first() === $days->last() ? self::seen() : self::seen(
            new ContentTotal(Dimension::Post, self::POST, 5, 4),
            new ContentTotal(Dimension::Page, self::PAGE, 3, 2),
            new ContentTotal(Dimension::Page, self::OTHER_PAGE, 2, 2),
        ));
        $asked = [];
        $names = self::createStub(ContentNames::class);
        $names->method('namesOf')->willReturnCallback(static function (Dimension $kind, array $ids) use (&$asked): array {
            $asked[] = [$kind, $ids];

            return [];
        });

        new VisitReportFinder(Period::Week->days())($visitCounts, $names);

        self::assertSame([[Dimension::Post, [self::POST]], [Dimension::Page, [self::PAGE, self::OTHER_PAGE]]], $asked);
    }

    /**
     * Only the most seen contents are shown apart: those left out add up in
     * one last line under no value, and the report tells how many they are.
     */
    public function testItGroupsTheContentsNotShownApartInOneLine(): void
    {
        $report = self::report([], contents: new SeenContents(
            [new ContentTotal(Dimension::Post, self::POST, 9, 7), new ContentTotal(Dimension::Page, self::PAGE, 4, 3)],
            5,
            20,
            15,
        ));

        self::assertEquals([
            new ReportRow(self::POST, 'Un post', 'post', 9, 7),
            new ReportRow(self::PAGE, 'Sobre mí', 'page', 4, 3),
            new ReportRow(null, null, null, 7, 5),
        ], $report->period->contents);
        self::assertSame(3, $report->period->otherContents);
    }

    /**
     * It reads the period ending yesterday, and today apart, and of each
     * as many contents as it shows apart.
     */
    public function testItReadsThePeriodEndingYesterdayAndTodayApart(): void
    {
        $asked = [];
        $contentsAsked = [];
        $visitCounts = $this->createMock(VisitCounts::class);
        $visitCounts->expects(self::exactly(2))->method('during')->willReturnCallback(static function (Days $days) use (&$asked): array {
            $asked[] = [$days->first(), $days->last()];

            return [];
        });
        $visitCounts->expects(self::exactly(2))->method('contentsDuring')->willReturnCallback(static function (Days $days, int $mostSeen) use (&$contentsAsked): SeenContents {
            $contentsAsked[] = [$days->first(), $days->last(), $mostSeen];

            return self::seen();
        });
        $visitCounts->method('oldestUncountedDay')->willReturn(null);

        new VisitReportFinder(Period::Week->days())($visitCounts, self::titles());

        self::assertSame([['2026-11-02', '2026-11-08'], ['2026-11-09', '2026-11-09']], $asked);
        self::assertSame([['2026-11-02', '2026-11-08', 50], ['2026-11-09', '2026-11-09', 50]], $contentsAsked);
    }

    public function testItAddsUpTheDaysOfThePeriod(): void
    {
        $report = self::report([
            CountMother::create(day: '2026-11-02', views: 10, visitors: 8),
            CountMother::create(day: '2026-11-05', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Device, value: 'mobile', views: 7, visitors: 6),
            CountMother::create(day: '2026-11-05', dimension: Dimension::Device, value: 'mobile', views: 2, visitors: 2),
            CountMother::create(day: '2026-11-02', dimension: Dimension::Device, value: 'desktop', views: 3, visitors: 2),
        ]);

        self::assertSame([12, 10], [$report->period->views, $report->period->visitors]);
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
            // Another dimension adds no view to the days.
            CountMother::create(day: '2026-11-05', dimension: Dimension::Device, value: 'mobile', views: 2),
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
        $report = self::report(
            [CountMother::create(views: 10, visitors: 8)],
            today: [CountMother::create(day: '2026-11-09')],
            contents: self::seen(new ContentTotal(Dimension::Page, self::PAGE, 10, 8)),
            todayContents: self::seen(new ContentTotal(Dimension::Page, self::OTHER_PAGE, 1, 1)),
        );

        self::assertSame([1, 1], [$report->today->views, $report->today->visitors]);
        self::assertEquals([new ReportRow(self::OTHER_PAGE, 'Otra página', 'page', 1, 1)], $report->today->contents);
    }

    public function testItReportsNothingWhenNothingWasCounted(): void
    {
        $report = self::report([]);

        self::assertSame([0, 0, [], 0, [], [], [], []], [$report->period->views, $report->period->visitors, $report->period->contents, $report->period->otherContents, $report->period->channels, $report->period->sources, $report->period->devices, $report->period->countries]);
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
        $visitCounts->expects(self::never())->method('contentsDuring');

        self::assertTells('errors.analytics.unknown_period', ['%days%' => 14], static fn () => new VisitReportFinder(14)($visitCounts, self::titles()));
    }

    /**
     * @param list<Count> $counts those of the period
     * @param list<Count> $today  those of today
     */
    private static function report(
        array $counts,
        array $today = [],
        ?string $uncounted = null,
        Period $period = Period::Week,
        ?SeenContents $contents = null,
        ?SeenContents $todayContents = null,
    ): VisitReport {
        $visitCounts = self::createStub(VisitCounts::class);
        // Today is the only run of days that ends where it begins.
        $visitCounts->method('during')->willReturnCallback(static fn (Days $days): array => $days->first() === $days->last() ? $today : $counts);
        $visitCounts->method('contentsDuring')->willReturnCallback(static fn (Days $days): SeenContents => ($days->first() === $days->last() ? $todayContents : $contents) ?? self::seen());
        $visitCounts->method('oldestUncountedDay')->willReturn($uncounted);

        return new VisitReportFinder($period->days())($visitCounts, self::titles());
    }

    /**
     * The contents seen, every one shown apart.
     */
    private static function seen(ContentTotal ...$contents): SeenContents
    {
        return new SeenContents(
            array_values($contents),
            \count($contents),
            array_sum(array_map(static fn (ContentTotal $content): int => $content->views, $contents)),
            array_sum(array_map(static fn (ContentTotal $content): int => $content->visitors, $contents)),
        );
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
     * The names of what is still on the CMS: two pages, a post and a tag;
     * the deleted one has none.
     */
    private static function titles(): ContentNames
    {
        $titles = self::createStub(ContentNames::class);
        $titles->method('namesOf')->willReturnCallback(static fn (Dimension $kind, array $ids): array => array_filter(
            match ($kind) {
                Dimension::Page => [self::PAGE => ['name' => 'Sobre mí', 'kind' => 'page'], self::OTHER_PAGE => ['name' => 'Otra página', 'kind' => 'page']],
                Dimension::Post => [self::POST => ['name' => 'Un post', 'kind' => 'post']],
                Dimension::Term => [self::TAG => ['name' => 'Sueño', 'kind' => 'tag']],
                default => [],
            },
            static fn (string $id): bool => \in_array($id, $ids, true),
            \ARRAY_FILTER_USE_KEY,
        ));

        return $titles;
    }
}
