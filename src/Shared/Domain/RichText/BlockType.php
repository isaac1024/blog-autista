<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

enum BlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case Quote = 'quote';
    case List = 'list';
    case Callout = 'callout';
    case Table = 'table';
    case Image = 'image';
}
