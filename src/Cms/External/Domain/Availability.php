<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Whether the address of an external resource still leads to it, as it was
 * last checked: the site paints an unavailable resource nowhere, as if it
 * were off the site, while it stays published.
 */
enum Availability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
