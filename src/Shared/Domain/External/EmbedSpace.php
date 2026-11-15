<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * The room an embedded resource takes on the page, kept for it before it
 * loads, so nothing moves when it does: a proportion of its width, a fixed
 * height, or the height its frame reports once it has painted itself,
 * which the facade holds until then.
 */
enum EmbedSpace: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';
    case Compact = 'compact';
    case Tall = 'tall';
    case Reported = 'reported';
}
