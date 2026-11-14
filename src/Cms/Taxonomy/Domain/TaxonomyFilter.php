<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

/**
 * Filters terms by their taxonomy.
 */
final readonly class TaxonomyFilter extends Filter
{
    public static function equalTo(Taxonomy $taxonomy): self
    {
        return new self('taxonomy', FilterOperator::Equal, $taxonomy->value);
    }
}
