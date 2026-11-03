<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

/**
 * Library-agnostic description of which aggregates to read: the filters they
 * must match, their order and the slice of results to return.
 *
 * The slice is always bounded: the limit is mandatory and capped, so no
 * caller can end up reading a whole table in one query.
 */
final readonly class Criteria
{
    public const int MAX_LIMIT = 100;

    /**
     * @param list<Filter> $filters
     */
    public function __construct(
        public int $limit,
        public array $filters,
        public ?Order $order,
        public int $offset,
    ) {
        if ($limit < 1) {
            throw InvalidCriteria::nonPositiveLimit($limit);
        }

        if ($limit > self::MAX_LIMIT) {
            throw InvalidCriteria::limitTooHigh($limit, self::MAX_LIMIT);
        }

        if ($offset < 0) {
            throw InvalidCriteria::negativeOffset($offset);
        }
    }
}
