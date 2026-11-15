<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters posts by their id.
 */
final readonly class IdFilter extends Filter
{
    /**
     * Every post but the one given.
     */
    public static function isNot(PostId $id): self
    {
        return new self('id', FilterOperator::NotEqual, $id->value());
    }
}
