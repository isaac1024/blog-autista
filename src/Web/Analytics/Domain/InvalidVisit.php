<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Shared\Domain\DomainError;

final class InvalidVisit extends DomainError
{
    public static function tooLong(string $field, int $max): self
    {
        return new self(\sprintf('The %s of a visit is longer than %d characters.', $field, $max), 'errors.visit.too_long', ['%field%' => $field, '%max%' => $max]);
    }

    public static function unknownContentType(string $type): self
    {
        return new self(\sprintf('The visits of "%s" are not counted.', $type), 'errors.visit.unknown_content_type', ['%type%' => $type]);
    }
}
