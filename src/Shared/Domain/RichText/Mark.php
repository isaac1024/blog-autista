<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Inline formatting and internal links. There are no italics, harder to read
 * for people with dyslexia, nor underlining apart from an actual link.
 */
enum Mark: string
{
    case Bold = 'bold';
    case Link = 'link';
}
