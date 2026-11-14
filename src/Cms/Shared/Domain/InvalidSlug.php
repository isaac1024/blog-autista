<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

use Shared\Domain\DomainError;

final class InvalidSlug extends DomainError
{
    public static function malformed(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid slug.', $value), 'errors.slug.malformed', ['%slug%' => $value]);
    }

    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The slug must have at least %d characters.', $minLength), 'errors.slug.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The slug must not exceed %d characters.', $maxLength), 'errors.slug.too_long', ['%max%' => $maxLength]);
    }
}
