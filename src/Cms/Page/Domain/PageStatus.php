<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Whether a page is on the site. A draft has never been published; an
 * unpublished page was, and has been taken off.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
