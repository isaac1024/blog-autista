<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Whether an external resource is on the site. A draft has never been
 * published; an unpublished resource was, and has been taken off.
 */
enum ResourceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
