<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters terms by their name.
 */
final readonly class NameFilter extends Filter
{
    /**
     * Terms whose name has the text anywhere: names are short, as short as
     * an acronym, which a full-text index leaves out.
     */
    public static function contains(string $text): self
    {
        return new self('name', FilterOperator::Contains, $text);
    }
}
