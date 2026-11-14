<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * What the site counted over some days, added up by what it counts.
 *
 * - Readers are counted per day, so over many days they add up those of
 *   each day: one who came back counts again.
 * - Each breakdown comes most seen first, and by value among equals; the
 *   contents, of every kind together, by kind and id among equals.
 * - The channels leave out the pages reached from within the site: they
 *   count entries to it.
 * - Sources with fewer entries than SMALLEST_SOURCE are grouped last under
 *   no value, so no small site, perhaps a reader's own, stands out.
 * - An unknown country counts under no value.
 */
final readonly class Counts
{
    public const int SMALLEST_SOURCE = 3;

    private const string WITHIN_THE_SITE = 'internal';

    /**
     * @param list<Count> $counts
     */
    public function __construct(private array $counts) {}

    public function site(): Total
    {
        return $this->totalsOf(Dimension::Site)[0] ?? new Total(null, 0, 0);
    }

    /**
     * The pages, the posts, the sections and tags and the authors seen,
     * all together.
     *
     * @return list<ContentTotal>
     */
    public function contents(): array
    {
        $contents = [];

        foreach (Dimension::contents() as $kind) {
            foreach ($this->totalsOf($kind) as $total) {
                // A content is counted under its id, never under no value.
                $contents[] = new ContentTotal($kind, $total->value ?? '', $total->views, $total->visitors);
            }
        }

        usort($contents, static fn (ContentTotal $one, ContentTotal $other): int => [$other->views, $one->kind->value, $one->id] <=> [$one->views, $other->kind->value, $other->id]);

        return $contents;
    }

    /**
     * @return list<Total>
     */
    public function channels(): array
    {
        return array_values(array_filter($this->totalsOf(Dimension::Channel), static fn (Total $total): bool => self::WITHIN_THE_SITE !== $total->value));
    }

    /**
     * @return list<Total>
     */
    public function sources(): array
    {
        $shown = [];
        $rest = null;

        foreach ($this->totalsOf(Dimension::Source) as $total) {
            if ($total->views >= self::SMALLEST_SOURCE) {
                $shown[] = $total;

                continue;
            }

            $rest = new Total(null, ($rest->views ?? 0) + $total->views, ($rest->visitors ?? 0) + $total->visitors);
        }

        return null === $rest ? $shown : [...$shown, $rest];
    }

    /**
     * @return list<Total>
     */
    public function devices(): array
    {
        return $this->totalsOf(Dimension::Device);
    }

    /**
     * @return list<Total>
     */
    public function countries(): array
    {
        return array_map(static fn (Total $total): Total => '' === $total->value ? new Total(null, $total->views, $total->visitors) : $total, $this->totalsOf(Dimension::Country));
    }

    /**
     * The pages seen on each of the days, those with none included.
     *
     * @return array<string, int> by day, oldest first
     */
    public function viewsByDay(Days $days): array
    {
        $views = array_fill_keys($days->each(), 0);

        foreach ($this->counts as $count) {
            if (Dimension::Site === $count->dimension && \array_key_exists($count->day, $views)) {
                $views[$count->day] += $count->views;
            }
        }

        return $views;
    }

    /**
     * The counts of a dimension added up by value over the days.
     *
     * @return list<Total>
     */
    private function totalsOf(Dimension $dimension): array
    {
        $totals = [];

        foreach ($this->counts as $count) {
            if ($dimension !== $count->dimension) {
                continue;
            }

            // Keyed apart from the value, so one made of digits stays a string.
            $total = $totals['='.$count->value] ?? new Total($count->value, 0, 0);
            $totals['='.$count->value] = new Total($count->value, $total->views + $count->views, $total->visitors + $count->visitors);
        }

        usort($totals, static fn (Total $one, Total $other): int => [$other->views, $one->value] <=> [$one->views, $other->value]);

        return $totals;
    }
}
