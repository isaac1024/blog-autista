<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

enum FilterOperator
{
    case Equal;
    case NotEqual;
    case Contains;

    /**
     * Full-text search: every word must appear, in any order and as a word
     * prefix. Results are ranked by relevance.
     */
    case Matches;
}
