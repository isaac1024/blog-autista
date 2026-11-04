<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * What search engines may do with a page: list it in their results, and
 * follow its links to discover others.
 */
enum Robots: string
{
    case IndexFollow = 'index,follow';
    case NoindexFollow = 'noindex,follow';
    case NoindexNofollow = 'noindex,nofollow';
}
