<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Whether an author is on the site. A draft has never been published; an
 * unpublished author was, and has been taken off.
 */
enum AuthorStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
