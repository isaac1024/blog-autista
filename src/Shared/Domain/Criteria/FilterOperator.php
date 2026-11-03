<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

enum FilterOperator
{
    case Equal;
    case NotEqual;
    case GreaterThan;
    case GreaterThanOrEqual;
    case LessThan;
    case LessThanOrEqual;
    case Contains;
    case StartsWith;
    case EndsWith;

    /**
     * Full-text search: every word must appear, in any order and as a word
     * prefix. Results are ranked by relevance.
     */
    case Matches;
}
