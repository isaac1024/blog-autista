<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

/**
 * What search engines may do with something the site serves: list it in
 * their results, and follow its links to discover others.
 */
enum Robots: string
{
    case IndexFollow = 'index,follow';
    case NoindexFollow = 'noindex,follow';
    case NoindexNofollow = 'noindex,nofollow';
}
