<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\ColumnChart;

/**
 * How a chart of columns scales its figures: the highest one reaches the
 * top of the axis, which says it as it is shown, and each column is as
 * tall as its share of it.
 */
final class ColumnChartTest extends TestCase
{
    public function testItScalesEveryColumnToTheHighestFigure(): void
    {
        $chart = self::chart([3, 9, 1]);

        self::assertSame(9, $chart->peak());
        self::assertSame('9 visitas', $chart->peakShown());
        self::assertSame([33.33, 100.0, 11.11, 0.0], [$chart->height(3), $chart->height(9), $chart->height(1), $chart->height(0)]);
    }

    /**
     * With no figure above none, the axis still reaches one, so no column
     * divides by nothing, and says it as a plain number.
     */
    public function testItReachesOneWhenEveryFigureIsNone(): void
    {
        foreach ([[0, 0], []] as $figures) {
            $chart = self::chart($figures);

            self::assertSame(1, $chart->peak());
            self::assertSame('1', $chart->peakShown());
            self::assertSame(0.0, $chart->height(0));
        }
    }

    /**
     * A highest figure of one is reached by its own column.
     */
    public function testItTellsAHighestFigureOfOneAsItIsShown(): void
    {
        $chart = self::chart([1, 0]);

        self::assertSame('1 visitas', $chart->peakShown());
        self::assertSame(100.0, $chart->height(1));
    }

    /**
     * Its heading is named by an id of its own, the same for the same title.
     */
    public function testItNamesItsHeadingAfterItsTitle(): void
    {
        self::assertSame('chart-924be283', self::chart([], 'Visitas por día')->headingId());
        self::assertSame('chart-08ed1f7b', self::chart([], 'Datos del blog')->headingId());
    }

    /**
     * @param list<int> $figures
     */
    private static function chart(array $figures, string $title = 'Visitas por día'): ColumnChart
    {
        $chart = new ColumnChart();
        $chart->mount($title, 'Tabla', 'Día', 'Visitas', array_map(
            static fn (int $figure): array => ['label' => 'día', 'value' => $figure, 'shown' => $figure.' visitas'],
            $figures,
        ));

        return $chart;
    }
}
