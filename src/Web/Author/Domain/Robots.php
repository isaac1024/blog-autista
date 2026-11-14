<?php

declare(strict_types=1);

namespace Web\Author\Domain;

/**
 * Whether search engines may list the page of an author and follow its links.
 */
enum Robots: string
{
    case IndexFollow = 'index,follow';
    case NoindexFollow = 'noindex,follow';
    case NoindexNofollow = 'noindex,nofollow';
}
