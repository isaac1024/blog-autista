<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\DataTable;

/**
 * How wide the bar of a row's share is drawn.
 */
final class DataTableTest extends TestCase
{
    public function testItDrawsAShareInWholePercentOfTheRow(): void
    {
        $table = new DataTable();

        self::assertSame([0, 33, 50, 100], [$table->width(0.0), $table->width(0.3333), $table->width(0.495), $table->width(1.0)]);
    }

    /**
     * A share past either end of the row stays within it.
     */
    public function testItKeepsAShareWithinTheRow(): void
    {
        $table = new DataTable();

        self::assertSame([0, 100], [$table->width(-0.2), $table->width(1.5)]);
    }
}
