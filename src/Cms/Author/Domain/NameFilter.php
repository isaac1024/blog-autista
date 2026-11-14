<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters authors by their name.
 */
final readonly class NameFilter extends Filter
{
    /**
     * Authors whose name has the text anywhere.
     */
    public static function contains(string $text): self
    {
        return new self('name', FilterOperator::Contains, $text);
    }
}
