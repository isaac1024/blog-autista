<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters external resources by their title.
 */
final readonly class TitleFilter extends Filter
{
    /**
     * Resources whose title has the text anywhere.
     */
    public static function contains(string $text): self
    {
        return new self('title', FilterOperator::Contains, $text);
    }
}
