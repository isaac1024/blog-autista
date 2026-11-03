<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters pages by their title.
 */
final readonly class TitleFilter extends Filter
{
    /**
     * Pages whose title has every word of the text, in any order.
     */
    public static function matches(string $text): self
    {
        return new self('title', FilterOperator::Matches, $text);
    }
}
