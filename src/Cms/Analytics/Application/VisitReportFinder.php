<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\ContentTotal;
use Cms\Analytics\Domain\Counts;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\SeenContents;
use Cms\Analytics\Domain\Total;
use Cms\Analytics\Domain\VisitCounts;

/**
 * What the site counted of its visits over the period of the days given,
 * ending yesterday, and today so far. Of the contents, only the most seen
 * are shown apart, and named; the rest add up in one last line.
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
        $today = Days::today();

        return new VisitReport(
            self::summary($period, $visitCounts->contentsDuring($days, SeenContents::SHOWN_APART), $contentNames),
            array_map(static fn (string $day, int $views): DayViews => new DayViews($day, $views), array_keys($views), $views),
            self::summary(new Counts($visitCounts->during($today)), $visitCounts->contentsDuring($today, SeenContents::SHOWN_APART), $contentNames),
            null !== $uncounted && Days::isOverdue($uncounted) ? $uncounted : null,
        );
    }

    private static function summary(Counts $counts, SeenContents $seen, ContentNames $contentNames): VisitSummary
    {
        $site = $counts->site();
        $contents = $seen->mostSeen;
        $others = $seen->others();
        $ids = [];
        $names = [];

        foreach ($contents as $content) {
            $ids[$content->kind->value][] = $content->id;
        }

        // Each kind is named at once, by the ids of its own.
        foreach ($ids as $kind => $ofKind) {
            $names[$kind] = $contentNames->namesOf(Dimension::from($kind), $ofKind);
        }

        $rows = array_map(static function (ContentTotal $content) use ($names): ReportRow {
            $name = $names[$content->kind->value][$content->id] ?? null;

            return new ReportRow($content->id, $name['name'] ?? null, $name['kind'] ?? $content->kind->value, $content->views, $content->visitors);
        }, $contents);

        return new VisitSummary(
            $site->views,
            $site->visitors,
            null === $others ? $rows : [...$rows, new ReportRow(null, null, null, $others->views, $others->visitors)],
            $others->count ?? 0,
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
