<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

/**
 * Condition a field must satisfy to be part of the results.
 *
 * Each context extends it with one final filter per searchable field, whose
 * named constructors expose only the operators and value types that field
 * supports, so callers can't build filters the domain doesn't allow.
 */
abstract readonly class Filter
{
    protected function __construct(
        public string $field,
        public FilterOperator $operator,
        public bool|float|int|string $value,
    ) {}
}
