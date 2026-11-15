<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * What checking an address found, as CheckOutcome tells it.
 */
enum CheckResult: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Moved = 'moved';
    case Doubtful = 'doubtful';
}
