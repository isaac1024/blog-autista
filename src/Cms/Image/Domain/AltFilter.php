<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters images by their alternative text.
 */
final readonly class AltFilter extends Filter
{
    /**
     * Images whose alternative text has every word of the text, in any order.
     */
    public static function matches(string $text): self
    {
        return new self('alt', FilterOperator::Matches, $text);
    }
}
