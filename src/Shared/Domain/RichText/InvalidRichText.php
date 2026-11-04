<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

use Shared\Domain\DomainError;

final class InvalidRichText extends DomainError
{
    public static function malformed(string $path, string $expected): self
    {
        return new self(\sprintf('The rich text at "%s" must be %s.', $path, $expected));
    }

    public static function unsupportedVersion(mixed $version): self
    {
        return new self(\sprintf('Version %s of the rich text format is not supported.', get_debug_type($version)));
    }

    public static function empty(string $path): self
    {
        return new self(\sprintf('The rich text at "%s" must not be empty.', $path));
    }

    public static function skippedHeadingLevel(int $level, int $previous): self
    {
        return new self(\sprintf('A level %d heading cannot follow a level %d one: heading levels go down one at a time.', $level, $previous));
    }

    public static function unevenTable(string $path, int $columns): self
    {
        return new self(\sprintf('Every row of the table at "%s" must have %d cells, as many as its header.', $path, $columns));
    }

    public static function notAllowed(string $feature): self
    {
        return new self(\sprintf('%s is not allowed here.', $feature));
    }
}
