<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * Whether an image is on the site. A draft has never been published; an
 * unpublished image was, and has been taken off.
 */
enum ImageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
