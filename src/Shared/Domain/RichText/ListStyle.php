<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

enum ListStyle: string
{
    case Unordered = 'unordered';
    case Ordered = 'ordered';
}
