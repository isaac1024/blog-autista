<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Application;

use Cms\Analytics\Application\DayViews;
use Cms\Analytics\Application\ReportRow;
use Cms\Analytics\Application\VisitReport;
use Cms\Analytics\Application\VisitReportFinder;
use Cms\Analytics\Domain\PageTitles;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageMother;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The report read from the tables the site counts its visits in: the days
 * it counted, and those it still keeps the visits of, counted as read.
 */
final class VisitReportFinderTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105f001';
    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105f002';
    private const string DELETED_PAGE = '01a1b801-ce58-723d-8234-b76d9105f003';

    public function testItAddsUpTheDaysOfThePeriodUpToYesterday(): void
    {
        $report = $this->report();

        self::assertSame([12, 10], [$report->period->views, $report->period->visitors]);
        self::assertSame(self::rows([
            new ReportRow(self::PAGE, 'Sobre mí', 8, 7),
            new ReportRow(self::DELETED_PAGE, null, 4, 3),
        ]), self::rows($report->period->pages));
        self::assertSame(self::rows([new ReportRow('search', null, 7, 6), new ReportRow('direct', null, 2, 2)]), self::rows($report->period->channels));
        self::assertSame(self::rows([new ReportRow('mobile', null, 9, 8), new ReportRow('desktop', null, 3, 2)]), self::rows($report->period->devices));
    }

    /**
     * Sources too small to show apart are grouped last; the cut is three
     * entries over the days read.
     */
    public function testItGroupsTheSmallestSources(): void
    {
        $report = $this->report();

        self::assertSame(self::rows([
            new ReportRow('google.es', null, 6, 5),
            new ReportRow('foro.example.org', null, 3, 3),
            new ReportRow(null, null, 3, 2),
        ]), self::rows($report->period->sources));
    }

    public function testItTellsAnUnknownCountryApart(): void
    {
        $report = $this->report();

        self::assertSame(self::rows([
            new ReportRow('ES', null, 6, 5),
            new ReportRow(null, null, 4, 3),
            new ReportRow('MX', null, 2, 2),
        ]), self::rows($report->period->countries));
    }

    public function testItGivesEveryDayOfThePeriodEvenWithNoVisit(): void
    {
        $report = $this->report();

        self::assertSame(self::days([
            new DayViews('2026-11-02', 10),
            new DayViews('2026-11-03', 0),
            new DayViews('2026-11-04', 0),
            new DayViews('2026-11-05', 2),
            new DayViews('2026-11-06', 0),
            new DayViews('2026-11-07', 0),
            new DayViews('2026-11-08', 0),
        ]), self::days($report->days));
    }

    public function testItCountsTodayApart(): void
    {
        $report = $this->report();

        self::assertSame([1, 1], [$report->today->views, $report->today->visitors]);
        self::assertSame(self::rows([new ReportRow(self::OTHER_PAGE, 'Otra página', 1, 1)]), self::rows($report->today->pages));
        self::assertSame(self::rows([new ReportRow('direct', null, 1, 1)]), self::rows($report->today->channels));
    }

    /**
     * A day before yesterday still kept means the nightly count is late.
     */
    public function testItTellsTheOldestDayTheNightlyCountMissed(): void
    {
        self::assertSame('2026-11-05', $this->report()->uncountedSince);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function nightsOfYesterday(): iterable
    {
        yield 'before the count is done' => ['2026-11-09 02:59:59 Europe/Madrid', null];
        yield 'once it is done' => ['2026-11-09 03:00:00 Europe/Madrid', '2026-11-08'];
        yield 'once it is done, told in universal time' => ['2026-11-09 02:00:00 UTC', '2026-11-08'];
    }

    /**
     * Yesterday is only late once the night's count is done.
     */
    #[DataProvider('nightsOfYesterday')]
    public function testYesterdayIsLateOnceTheNightIsOver(string $now, ?string $uncountedSince): void
    {
        self::bootKernel();
        self::mockTime($now);
        self::getContainer()->get(Connection::class)->insert('web_visit', ['id' => '01a1b801-ce58-723d-8234-b76d9105f104', 'page_id' => self::PAGE, 'day' => '2026-11-08', 'channel' => 'direct', 'source' => null, 'reader' => str_repeat('a', 64), 'device' => 'desktop', 'country' => null]);

        $report = new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class));

        self::assertSame($uncountedSince, $report->uncountedSince);
    }

    public function testTodayIsNeverLate(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-09 23:59:59 Europe/Madrid');
        self::getContainer()->get(Connection::class)->insert('web_visit', ['id' => '01a1b801-ce58-723d-8234-b76d9105f105', 'page_id' => self::PAGE, 'day' => '2026-11-09', 'channel' => 'direct', 'source' => null, 'reader' => str_repeat('a', 64), 'device' => 'desktop', 'country' => null]);

        $report = new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class));

        self::assertNull($report->uncountedSince);
    }

    public function testItReportsNothingWhenNothingWasCounted(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-09 10:00:00 Europe/Madrid');

        $report = new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class));

        self::assertSame([0, 0, [], [], [], [], []], [$report->period->views, $report->period->visitors, $report->period->pages, $report->period->channels, $report->period->sources, $report->period->devices, $report->period->countries]);
        self::assertCount(7, $report->days);
        self::assertNull($report->uncountedSince);
    }

    public function testThePeriodsLookAsFarBackAsTheySay(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-09 10:00:00 Europe/Madrid');

        foreach ([Period::Week, Period::Month, Period::Quarter] as $period) {
            $days = new VisitReportFinder($period)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class))->days;

            self::assertCount($period->days(), $days);
            self::assertSame('2026-11-08', $days[\count($days) - 1]->day);
        }
    }

    /**
     * The days are Madrid's: at 23:30 in universal time, it is already the
     * next day there, and yesterday with it.
     */
    public function testItTellsTheDaysAsMadridDoes(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-08 23:30:00 UTC');

        $days = new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class))->days;

        self::assertSame('2026-11-08', $days[\count($days) - 1]->day);
    }

    /**
     * With no source too small, none is grouped; every page still on the
     * CMS has its name.
     */
    public function testItGroupsNoSourceWhenNoneIsTooSmall(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-09 10:00:00 Europe/Madrid');
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::create(id: self::OTHER_PAGE, title: 'Otra página', slug: 'otra-pagina'));
        $connection = self::getContainer()->get(Connection::class);
        foreach ([
            ['source', 'google.es', 5, 4],
            ['source', 'foro.example.org', 3, 3],
            ['page', self::PAGE, 5, 4],
            ['page', self::OTHER_PAGE, 3, 3],
        ] as [$dimension, $value, $views, $visitors]) {
            $connection->insert('web_visit_total', ['day' => '2026-11-08', 'dimension' => $dimension, 'value' => $value, 'views' => $views, 'visitors' => $visitors]);
        }

        $report = new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class));

        self::assertSame(self::rows([new ReportRow('google.es', null, 5, 4), new ReportRow('foro.example.org', null, 3, 3)]), self::rows($report->period->sources));
        self::assertSame(self::rows([new ReportRow(self::PAGE, 'Sobre mí', 5, 4), new ReportRow(self::OTHER_PAGE, 'Otra página', 3, 3)]), self::rows($report->period->pages));
    }

    /**
     * @param list<ReportRow> $rows
     *
     * @return list<array{?string, ?string, int, int}>
     */
    private static function rows(array $rows): array
    {
        return array_map(static fn (ReportRow $row): array => [$row->value, $row->name, $row->views, $row->visitors], $rows);
    }

    /**
     * @param list<DayViews> $days
     *
     * @return list<array{string, int}>
     */
    private static function days(array $days): array
    {
        return array_map(static fn (DayViews $day): array => [$day->day, $day->views], $days);
    }

    private function report(): VisitReport
    {
        self::bootKernel();
        self::mockTime('2026-11-09 10:00:00 Europe/Madrid');
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::create(id: self::OTHER_PAGE, title: 'Otra página', slug: 'otra-pagina'));
        $connection = self::getContainer()->get(Connection::class);

        // Counted already: a day of the period, and one before it.
        foreach ([
            ['2026-11-01', 'site', '', 100, 100],
            ['2026-11-02', 'site', '', 10, 8],
            ['2026-11-02', 'page', self::PAGE, 6, 5],
            ['2026-11-02', 'page', self::DELETED_PAGE, 4, 3],
            ['2026-11-02', 'channel', 'search', 5, 4],
            ['2026-11-02', 'channel', 'internal', 3, 2],
            ['2026-11-02', 'channel', 'direct', 2, 2],
            ['2026-11-02', 'source', 'google.es', 4, 3],
            ['2026-11-02', 'source', 'foro.example.org', 3, 3],
            ['2026-11-02', 'source', 'blog.example.org', 2, 1],
            ['2026-11-02', 'source', 'otro.example.org', 1, 1],
            ['2026-11-02', 'device', 'mobile', 7, 6],
            ['2026-11-02', 'device', 'desktop', 3, 2],
            ['2026-11-02', 'country', 'ES', 6, 5],
            ['2026-11-02', 'country', '', 4, 3],
        ] as [$day, $dimension, $value, $views, $visitors]) {
            $connection->insert('web_visit_total', ['day' => $day, 'dimension' => $dimension, 'value' => $value, 'views' => $views, 'visitors' => $visitors]);
        }

        // Still kept: a day the nightly count missed, and today.
        foreach ([
            ['01a1b801-ce58-723d-8234-b76d9105f101', self::PAGE, '2026-11-05', 'search', 'google.es', 'a', 'mobile', 'MX'],
            ['01a1b801-ce58-723d-8234-b76d9105f102', self::PAGE, '2026-11-05', 'search', 'google.es', 'b', 'mobile', 'MX'],
            ['01a1b801-ce58-723d-8234-b76d9105f103', self::OTHER_PAGE, '2026-11-09', 'direct', null, 'a', 'desktop', 'ES'],
        ] as [$id, $page, $day, $channel, $source, $reader, $device, $country]) {
            $connection->insert('web_visit', ['id' => $id, 'page_id' => $page, 'day' => $day, 'channel' => $channel, 'source' => $source, 'reader' => str_repeat($reader, 64), 'device' => $device, 'country' => $country]);
        }

        return new VisitReportFinder(Period::Week)(self::getContainer()->get(VisitCounts::class), self::getContainer()->get(PageTitles::class));
    }
}
