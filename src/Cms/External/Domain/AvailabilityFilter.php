<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters external resources by whether their address still leads to them.
 */
final readonly class AvailabilityFilter extends Filter
{
    public static function equalTo(Availability $availability): self
    {
        return new self('availability', FilterOperator::Equal, $availability->value);
    }
}
