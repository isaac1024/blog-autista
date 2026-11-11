<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

use Cms\Analytics\Domain\Counts;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\PageTitles;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\Total;
use Cms\Analytics\Domain\VisitCounts;

/**
 * What the site counted of its visits over the period of the days given,
 * ending yesterday, and today so far.
 */
final readonly class VisitReportFinder
{
    public function __construct(private int $days) {}

    public function __invoke(VisitCounts $visitCounts, PageTitles $pageTitles): VisitReport
    {
        $days = Days::endingYesterday(Period::fromDays($this->days));
        $period = new Counts($visitCounts->during($days));
        $uncounted = $visitCounts->oldestUncountedDay();
        $views = $period->viewsByDay($days);

        return new VisitReport(
            self::summary($period, $pageTitles),
            array_map(static fn (string $day, int $views): DayViews => new DayViews($day, $views), array_keys($views), $views),
            self::summary(new Counts($visitCounts->during(Days::today())), $pageTitles),
            null !== $uncounted && Days::isOverdue($uncounted) ? $uncounted : null,
        );
    }

    private static function summary(Counts $counts, PageTitles $pageTitles): VisitSummary
    {
        $site = $counts->site();
        $pages = $counts->pages();
        // A page is counted under its id, never under no value.
        $titles = $pageTitles->titlesOf(array_map(static fn (Total $total): string => $total->value ?? '', $pages));

        return new VisitSummary(
            $site->views,
            $site->visitors,
            array_map(static fn (Total $total): ReportRow => self::row($total, $titles[$total->value ?? ''] ?? null), $pages),
            array_map(self::row(...), $counts->channels()),
            array_map(self::row(...), $counts->sources()),
            array_map(self::row(...), $counts->devices()),
            array_map(self::row(...), $counts->countries()),
        );
    }

    private static function row(Total $total, ?string $name = null): ReportRow
    {
        return new ReportRow($total->value, $name, $total->views, $total->visitors);
    }
}
