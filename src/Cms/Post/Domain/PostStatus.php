<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Whether a post is on the site. A draft has never been published; an
 * unpublished post was, and has been taken off.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
