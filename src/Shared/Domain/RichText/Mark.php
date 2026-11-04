<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Inline formatting. Only bold exists: italics and underlining are harder to
 * read for people with dyslexia, and underlined text passes for a link.
 */
enum Mark: string
{
    case Bold = 'bold';
}
