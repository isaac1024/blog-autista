<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\ContentTotal;
use Cms\Analytics\Domain\Counts;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
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

    public function __invoke(VisitCounts $visitCounts, ContentNames $contentNames): VisitReport
    {
        $days = Days::endingYesterday(Period::fromDays($this->days));
        $period = new Counts($visitCounts->during($days));
        $uncounted = $visitCounts->oldestUncountedDay();
        $views = $period->viewsByDay($days);

        return new VisitReport(
            self::summary($period, $contentNames),
            array_map(static fn (string $day, int $views): DayViews => new DayViews($day, $views), array_keys($views), $views),
            self::summary(new Counts($visitCounts->during(Days::today())), $contentNames),
            null !== $uncounted && Days::isOverdue($uncounted) ? $uncounted : null,
        );
    }

    private static function summary(Counts $counts, ContentNames $contentNames): VisitSummary
    {
        $site = $counts->site();
        $contents = $counts->contents();
        $names = [];

        foreach (Dimension::contents() as $kind) {
            $ids = array_map(
                static fn (ContentTotal $content): string => $content->id,
                array_values(array_filter($contents, static fn (ContentTotal $content): bool => $kind === $content->kind)),
            );
            $names[$kind->value] = [] === $ids ? [] : $contentNames->namesOf($kind, $ids);
        }

        return new VisitSummary(
            $site->views,
            $site->visitors,
            array_map(static function (ContentTotal $content) use ($names): ReportRow {
                $name = $names[$content->kind->value][$content->id] ?? null;

                return new ReportRow($content->id, $name['name'] ?? null, $name['kind'] ?? $content->kind->value, $content->views, $content->visitors);
            }, $contents),
            array_map(self::row(...), $counts->channels()),
            array_map(self::row(...), $counts->sources()),
            array_map(self::row(...), $counts->devices()),
            array_map(self::row(...), $counts->countries()),
        );
    }

    private static function row(Total $total): ReportRow
    {
        return new ReportRow($total->value, null, null, $total->views, $total->visitors);
    }
}
