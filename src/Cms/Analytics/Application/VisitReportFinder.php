<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\PageTitles;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;

/**
 * What the site counted of its visits over a period ending yesterday, and
 * today so far.
 */
final readonly class VisitReportFinder
{
    /**
     * Sources with fewer entries than this over the days read are grouped,
     * so no small site, perhaps a reader's own, stands out.
     */
    public const int SMALLEST_SOURCE = 3;

    private const string WITHIN_THE_SITE = 'internal';

    public function __construct(private Period $period) {}

    public function __invoke(VisitCounts $visitCounts, PageTitles $pageTitles): VisitReport
    {
        $days = Days::endingYesterday($this->period);
        $counts = $visitCounts->during($days);

        return new VisitReport(
            $this->summary($counts, $pageTitles),
            self::days($days, $counts),
            $this->summary($visitCounts->during(Days::today()), $pageTitles),
        );
    }

    /**
     * @param list<Count> $counts
     */
    private function summary(array $counts, PageTitles $pageTitles): VisitSummary
    {
        $site = self::rows($counts, Dimension::Site)[0] ?? new ReportRow(null, null, 0, 0);
        $pages = self::rows($counts, Dimension::Page);
        $titles = $pageTitles->titlesOf(array_values(array_filter(array_map(static fn (ReportRow $row): ?string => $row->value, $pages), static fn (?string $id): bool => null !== $id)));

        return new VisitSummary(
            $site->views,
            $site->visitors,
            array_map(static fn (ReportRow $row): ReportRow => new ReportRow($row->value, $titles[$row->value ?? ''] ?? null, $row->views, $row->visitors), $pages),
            array_values(array_filter(self::rows($counts, Dimension::Channel), static fn (ReportRow $row): bool => self::WITHIN_THE_SITE !== $row->value)),
            self::grouped(self::rows($counts, Dimension::Source)),
            self::rows($counts, Dimension::Device),
            array_map(static fn (ReportRow $row): ReportRow => '' === $row->value ? new ReportRow(null, null, $row->views, $row->visitors) : $row, self::rows($counts, Dimension::Country)),
        );
    }

    /**
     * The counts of a dimension added up by value over the days, most seen first.
     *
     * @param list<Count> $counts
     *
     * @return list<ReportRow>
     */
    private static function rows(array $counts, Dimension $dimension): array
    {
        $totals = [];

        foreach ($counts as $count) {
            if ($dimension !== $count->dimension) {
                continue;
            }

            // Keyed apart from the value, so one made of digits stays a string.
            [, $views, $visitors] = $totals['='.$count->value] ?? [$count->value, 0, 0];
            $totals['='.$count->value] = [$count->value, $views + $count->views, $visitors + $count->visitors];
        }

        $rows = [];

        foreach ($totals as [$value, $views, $visitors]) {
            $rows[] = new ReportRow($value, null, $views, $visitors);
        }

        usort($rows, static fn (ReportRow $one, ReportRow $other): int => [$other->views, $one->value] <=> [$one->views, $other->value]);

        return $rows;
    }

    /**
     * The sources big enough to show apart, then the rest under no value.
     *
     * @param list<ReportRow> $rows
     *
     * @return list<ReportRow>
     */
    private static function grouped(array $rows): array
    {
        $shown = array_values(array_filter($rows, static fn (ReportRow $row): bool => $row->views >= self::SMALLEST_SOURCE));
        $rest = array_filter($rows, static fn (ReportRow $row): bool => $row->views < self::SMALLEST_SOURCE);

        if ([] === $rest) {
            return $shown;
        }

        return [...$shown, new ReportRow(
            null,
            null,
            array_sum(array_map(static fn (ReportRow $row): int => $row->views, $rest)),
            array_sum(array_map(static fn (ReportRow $row): int => $row->visitors, $rest)),
        )];
    }

    /**
     * @param list<Count> $counts
     *
     * @return list<DayViews>
     */
    private static function days(Days $days, array $counts): array
    {
        $site = [];

        foreach ($counts as $count) {
            if (Dimension::Site === $count->dimension) {
                $site[$count->day] = $count;
            }
        }

        return array_map(
            static fn (string $day): DayViews => new DayViews($day, $site[$day]->views ?? 0),
            $days->each(),
        );
    }
}
