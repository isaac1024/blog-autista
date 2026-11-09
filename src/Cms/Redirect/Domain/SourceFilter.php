<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;
use Shared\Domain\SitePath;

final readonly class SourceFilter extends Filter
{
    /**
     * Redirects whose address has the text, written as their addresses are.
     */
    public static function contains(string $text): self
    {
        return new self('source', FilterOperator::Contains, SitePath::write($text));
    }
}
