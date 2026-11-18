<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * Outbound port that reads what the site counted of its visits: the days it
 * already counted, and those it still keeps the visits of, counted as read.
 */
interface VisitCounts
{
    /**
     * The counts of the whole site, its channels, sources, devices and
     * countries, day by day; never those of a content.
     *
     * @return list<Count>
     */
    public function during(Days $days): array;

    /**
     * The contents seen over the days, added up by kind and id, the most seen
     * first and by kind and id among equals, as many as given at most; and
     * what every content seen adds up to.
     */
    public function contentsDuring(Days $days, int $mostSeen): SeenContents;

    /**
     * The oldest day the site still keeps the visits of, not counted yet,
     * or none when it keeps none.
     */
    public function oldestUncountedDay(): ?string;
}
