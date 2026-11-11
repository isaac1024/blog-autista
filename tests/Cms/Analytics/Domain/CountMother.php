<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Domain;

use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Dimension;

/**
 * Builds the counts the site keeps for tests, with sensible defaults that
 * can be overridden through named arguments:
 *
 *     CountMother::create()
 *     CountMother::create(dimension: Dimension::Page, value: CountMother::PAGE, views: 6, visitors: 5)
 *
 * A count is of the whole site, yesterday of the clock the tests set, one
 * view by one reader, unless a test says otherwise.
 */
final class CountMother
{
    public const string DAY = '2026-11-08';
    public const string PAGE = '01a1b801-ce58-723d-8234-b76d9105f001';

    public static function create(
        string $day = self::DAY,
        Dimension $dimension = Dimension::Site,
        string $value = '',
        int $views = 1,
        int $visitors = 1,
    ): Count {
        return new Count($day, $dimension, $value, $views, $visitors);
    }
}
