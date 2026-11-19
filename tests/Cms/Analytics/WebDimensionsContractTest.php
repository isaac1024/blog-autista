<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics;

use Cms\Analytics\Domain\Dimension;
use PHPUnit\Framework\TestCase;
use Web\Analytics\Domain\Dimension as WebDimension;

/**
 * The CMS reads in shared_visit_total the dimensions the site counts visits
 * by: one the site added alone would be left out of the report without a
 * word. The report knows every dimension the site writes, and no other.
 */
final class WebDimensionsContractTest extends TestCase
{
    public function testTheReportKnowsTheDimensionsTheSiteCounts(): void
    {
        self::assertEqualsCanonicalizing(
            array_map(static fn (WebDimension $dimension): string => $dimension->value, WebDimension::cases()),
            array_map(static fn (Dimension $dimension): string => $dimension->value, Dimension::cases()),
        );
    }
}
