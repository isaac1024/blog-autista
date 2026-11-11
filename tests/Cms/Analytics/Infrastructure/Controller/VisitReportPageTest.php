<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Infrastructure\Controller;

use Cms\Analytics\Application\DayViews;
use Cms\Analytics\Application\ReportRow;
use Cms\Analytics\Application\VisitReport;
use Cms\Analytics\Application\VisitSummary;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Infrastructure\Controller\VisitReportPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Intl\CountryNames;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the report of the visits makes: which period it is of,
 * what it warns of, what it breaks the visits down by, and how it names
 * and tells each figure. Routes are generated as their names; the texts are
 * their translation keys.
 */
final class VisitReportPageTest extends TestCase
{
    /**
     * Every period can be chosen, by its days, the one shown marked.
     */
    public function testItOffersEveryPeriodWithTheOneShownMarked(): void
    {
        $report = $this->report(Period::Week, $this->visits());

        PageComponents::one($report, 'Shared:SegmentedNav', ['links' => [
            ['label' => 'cms.analytics.periods.days(%days%=7)', 'url' => '/cms_analytics?days=7', 'current' => true],
            ['label' => 'cms.analytics.periods.days(%days%=30)', 'url' => '/cms_analytics?days=30', 'current' => false],
            ['label' => 'cms.analytics.periods.days(%days%=90)', 'url' => '/cms_analytics?days=90', 'current' => false],
        ]]);
    }

    /**
     * While the nightly count is late, the report warns of it, from the day
     * it should have counted.
     */
    public function testItWarnsWhileTheNightlyCountIsLate(): void
    {
        $late = $this->report(Period::Month, $this->visits(uncountedSince: '2026-11-07'));
        $onTime = $this->report(Period::Month, $this->visits());

        PageComponents::one($late, 'Shared:Alert', [
            'title' => 'cms.analytics.uncounted.title',
            'message' => 'cms.analytics.uncounted.message(%day%=7/11/2026)',
        ]);
        self::assertSame([], PageComponents::find($onTime, 'Shared:Alert', []));
    }

    /**
     * A site with no visit counted, over the period nor today, tells so
     * instead of empty figures; a visit today is enough to report.
     */
    #[DataProvider('visitsCounted')]
    public function testItReportsOnlyWhenAnyVisitWasCounted(int $periodViews, int $todayViews, bool $reported): void
    {
        $report = $this->report(Period::Month, new VisitReport(
            $this->summary(views: $periodViews),
            [],
            $this->summary(views: $todayViews),
            null,
        ));

        self::assertSame(!$reported, [] !== PageComponents::find($report, 'Shared:EmptyState', []));
        // Today, then the period it is of.
        self::assertSame(
            $reported ? ['cms.analytics.today.title', 'cms.analytics.period.title(%days%=30)'] : [],
            array_map(static fn (PageComponent $panel): mixed => $panel->props['title'], PageComponents::find($report, 'Shared:Panel', [])),
        );
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function visitsCounted(): iterable
    {
        yield 'none' => [0, 0, false];
        yield 'only today' => [0, 1, true];
        yield 'only over the period' => [1, 0, true];
    }

    /**
     * Today is broken down by its pages and channels, the period by every
     * breakdown, and either only by those with any row.
     */
    public function testItBreaksTheVisitsDownByWhatHasAnyRow(): void
    {
        $page = [new ReportRow('01a1b801-ce58-723d-8234-b76d9105f001', 'Sobre mí', 1, 1)];
        $channel = [new ReportRow('search', null, 1, 1)];
        $source = [new ReportRow('google.es', null, 1, 1)];
        $device = [new ReportRow('mobile', null, 1, 1)];
        $country = [new ReportRow('AR', null, 1, 1)];
        $report = $this->report(Period::Month, new VisitReport(
            $this->summary(views: 1, pages: $page, channels: [], sources: $source, devices: $device, countries: $country),
            [],
            $this->summary(views: 1, pages: $page, channels: $channel, sources: $source, devices: $device, countries: $country),
            null,
        ));

        self::assertSame(
            [
                // Today
                'cms.analytics.pages.caption',
                'cms.analytics.channels.caption',
                // The period, without the channels it has no row of
                'cms.analytics.pages.caption',
                'cms.analytics.sources.caption',
                'cms.analytics.devices.caption',
                'cms.analytics.countries.caption',
            ],
            array_map(static fn (PageComponent $table): mixed => $table->props['caption'], PageComponents::find($report, 'Shared:DataTable', [])),
        );
    }

    /**
     * Each row is named for what it counts: a page by its title, or as
     * deleted; channels and devices by their own names; the smallest sources
     * together; a country by its name in the language, or as unknown.
     */
    public function testItNamesEveryRow(): void
    {
        $report = $this->report(Period::Month, new VisitReport(
            $this->summary(
                views: 1,
                pages: [new ReportRow('01a1b801-ce58-723d-8234-b76d9105f001', 'Sobre mí', 1, 1), new ReportRow('01a1b801-ce58-723d-8234-b76d9105f002', null, 1, 1)],
                channels: [new ReportRow('search', null, 1, 1)],
                sources: [new ReportRow('google.es', null, 1, 1), new ReportRow(null, null, 1, 1)],
                devices: [new ReportRow('mobile', null, 1, 1)],
                countries: [new ReportRow('AR', null, 1, 1), new ReportRow(null, null, 1, 1)],
            ),
            [],
            $this->summary(views: 0),
            null,
        ));

        self::assertSame(
            [
                ['Sobre mí', 'cms.analytics.pages.deleted'],
                ['cms.analytics.channel.search'],
                ['google.es', 'cms.analytics.sources.others'],
                ['cms.analytics.device.mobile'],
                ['Argentina', 'cms.analytics.countries.unknown'],
            ],
            array_map(
                static fn (PageComponent $table): array => array_column(self::rows($table), 'name'),
                PageComponents::find($report, 'Shared:DataTable', []),
            ),
        );
    }

    /**
     * Each row's bar is its share of the most seen one, which fills it
     * however few its views; a row with no view has none, even when no row
     * has any.
     */
    public function testItSharesEachRowAgainstTheMostSeen(): void
    {
        $report = $this->report(Period::Month, new VisitReport(
            $this->summary(
                views: 1,
                pages: [new ReportRow('a', 'A', 8, 1), new ReportRow('b', 'B', 2, 1)],
                channels: [new ReportRow('direct', null, 1, 1), new ReportRow('search', null, 0, 0)],
                sources: [new ReportRow('google.es', null, 0, 0)],
            ),
            [],
            $this->summary(views: 0),
            null,
        ));

        [$pages, $channels, $sources] = PageComponents::find($report, 'Shared:DataTable', []);
        self::assertSame([1, 0.25], array_column(self::rows($pages), 'share'));
        self::assertSame([1, 0], array_column(self::rows($channels), 'share'));
        self::assertSame([0], array_column(self::rows($sources), 'share'));
    }

    /**
     * Figures are told with the thousands separator of the language.
     */
    #[DataProvider('separatorsByLanguage')]
    public function testItTellsFiguresInTheLanguageOfTheEditor(string $locale, string $views, string $visitors): void
    {
        $report = $this->report(Period::Month, new VisitReport(
            $this->summary(views: 12345, visitors: 6789, pages: [new ReportRow('a', 'A', 12345, 6789)]),
            [new DayViews('2026-11-09', 12345)],
            $this->summary(views: 0),
            null,
        ), $locale);

        [, $period] = PageComponents::find($report, 'Shared:Stats', []);
        self::assertSame([$views, $visitors], array_column(self::items($period), 'value'));
        self::assertSame([['label' => '9/11/2026', 'value' => 12345, 'shown' => $views]], PageComponents::one($report, 'Shared:ColumnChart', [])->props['points']);
        self::assertSame([[$views, $visitors]], array_column(self::rows(PageComponents::one($report, 'Shared:DataTable', [])), 'figures'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function separatorsByLanguage(): iterable
    {
        yield 'Spanish' => ['es', '12.345', '6.789'];
        yield 'English' => ['en', '12,345', '6,789'];
    }

    /**
     * Today counts its visitors; the period adds up those of each day, and
     * says so, since whoever comes back another day counts again.
     */
    public function testItTellsTheVisitorsOfThePeriodAreAddedUpDayByDay(): void
    {
        $report = $this->report(Period::Month, new VisitReport($this->summary(views: 1), [], $this->summary(views: 1), null));

        [$today, $period] = PageComponents::find($report, 'Shared:Stats', []);
        self::assertSame(
            [['label' => 'cms.analytics.views', 'value' => '1'], ['label' => 'cms.analytics.visitors', 'value' => '0']],
            self::items($today),
        );
        self::assertSame(
            [['label' => 'cms.analytics.views', 'value' => '1'], ['label' => 'cms.analytics.daily_visitors', 'value' => '0', 'hint' => 'cms.analytics.daily_visitors_hint']],
            self::items($period),
        );
    }

    /**
     * @return list<array<mixed>>
     */
    private static function rows(PageComponent $table): array
    {
        $rows = $table->props['rows'];
        self::assertIsList($rows);
        self::assertContainsOnlyArray($rows);

        return $rows;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function items(PageComponent $stats): array
    {
        $items = $stats->props['items'];
        self::assertIsList($items);
        self::assertContainsOnlyArray($items);

        return $items;
    }

    private function visits(?string $uncountedSince = null): VisitReport
    {
        return new VisitReport($this->summary(views: 1), [], $this->summary(views: 1), $uncountedSince);
    }

    /**
     * @param list<ReportRow> $pages
     * @param list<ReportRow> $channels
     * @param list<ReportRow> $sources
     * @param list<ReportRow> $devices
     * @param list<ReportRow> $countries
     */
    private function summary(
        int $views,
        int $visitors = 0,
        array $pages = [],
        array $channels = [],
        array $sources = [],
        array $devices = [],
        array $countries = [],
    ): VisitSummary {
        return new VisitSummary($views, $visitors, $pages, $channels, $sources, $devices, $countries);
    }

    private function report(Period $period, VisitReport $report, string $locale = 'es'): Page
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale($locale);

        $page = new VisitReportPage(new RouteNameUrlGenerator(), $translator, new CountryNames())($period->days(), $report);
        // Whatever a test asks, the report gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
