<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

/**
 * Whether a term is on the site. A draft has never been published; an
 * unpublished term was, and has been taken off.
 */
enum TermStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
