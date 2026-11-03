<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

/**
 * Field the results are sorted by, and in which direction.
 */
final readonly class Order
{
    public function __construct(
        public string $field,
        public OrderDirection $direction,
    ) {}
}
