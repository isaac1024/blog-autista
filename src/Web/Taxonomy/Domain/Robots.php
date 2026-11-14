<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

/**
 * Whether search engines may list a term and follow its links.
 */
enum Robots: string
{
    case IndexFollow = 'index,follow';
    case NoindexFollow = 'noindex,follow';
    case NoindexNofollow = 'noindex,nofollow';
}
