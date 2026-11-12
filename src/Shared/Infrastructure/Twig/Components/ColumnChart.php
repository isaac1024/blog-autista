<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * One measure over time as columns rising from one baseline, in one hue,
 * with its peak on the axis, its first and last labels beneath, each
 * column's figure on hover, and the same figures as a table for whoever
 * can't see or read the columns.
 */
#[AsTwigComponent]
final class ColumnChart
{
    public string $title = '';

    public string $tableLabel = '';

    public string $columnLabel = '';

    public string $valueLabel = '';

    /**
     * @var list<array{label: string, value: int, shown: string}>
     */
    public array $points = [];

    /**
     * @param list<array{label: string, value: int, shown: string}> $points each column, oldest first: its label, its figure, and the figure as shown
     */
    public function mount(string $title, string $tableLabel, string $columnLabel, string $valueLabel, array $points): void
    {
        $this->title = $title;
        $this->tableLabel = $tableLabel;
        $this->columnLabel = $columnLabel;
        $this->valueLabel = $valueLabel;
        $this->points = $points;
    }

    /**
     * The highest figure, which the axis reaches; one when every figure is none.
     */
    public function peak(): int
    {
        return max([1, ...array_map(static fn (array $point): int => $point['value'], $this->points)]);
    }

    /**
     * The highest figure as shown, on top of the axis.
     */
    public function peakShown(): string
    {
        foreach ($this->points as $point) {
            if ($this->peak() === $point['value']) {
                return $point['shown'];
            }
        }

        return (string) $this->peak();
    }

    /**
     * How tall a column is, in whole percent of the plot: the stylesheet has
     * a class for each, since the content security policy refuses a style
     * attribute.
     */
    public function height(int $value): int
    {
        return (int) round(100 * $value / $this->peak());
    }

    public function headingId(): string
    {
        return 'chart-'.substr(md5($this->title), 0, 8);
    }
}
