<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Meaning of a callout, never just its colour: each variant is rendered with
 * its own visible label.
 */
enum CalloutVariant: string
{
    case Note = 'note';
    case Important = 'important';
}
