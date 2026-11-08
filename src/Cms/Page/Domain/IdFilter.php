<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters pages by their id.
 */
final readonly class IdFilter extends Filter
{
    /**
     * Every page but the one given.
     */
    public static function isNot(PageId $id): self
    {
        return new self('id', FilterOperator::NotEqual, $id->value());
    }
}
