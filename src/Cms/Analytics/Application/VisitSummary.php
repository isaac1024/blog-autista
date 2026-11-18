<?php

declare(strict_types=1);

namespace Cms\Analytics\Application;

/**
 * What the site counted over some days. Readers are counted per day, so over
 * many days they add up those of each day: one who came back counts again.
 * The channels and the sources count entries to the site, not pages seen
 * within it.
 *
 * Its constructor takes each figure and breakdown, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class VisitSummary
{
    /**
     * @param list<ReportRow> $contents      pages, posts, sections and tags, and authors, the most seen first; one deleted has no name; those not shown apart grouped last under no value
     * @param int             $otherContents how many contents the last line groups, none when there is no such line
     * @param list<ReportRow> $channels      most entries first, within the site left out
     * @param list<ReportRow> $sources       most entries first, the smallest grouped last under no value
     * @param list<ReportRow> $devices       most seen first
     * @param list<ReportRow> $countries     most seen first, an unknown one under no value
     */
    public function __construct(
        public int $views,
        public int $visitors,
        public array $contents,
        public int $otherContents,
        public array $channels,
        public array $sources,
        public array $devices,
        public array $countries,
    ) {}
}
