<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\DomainError;

final class InvalidAuthorName extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The name must have at least %d characters.', $minLength), 'errors.author.name.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The name must not exceed %d characters.', $maxLength), 'errors.author.name.too_long', ['%max%' => $maxLength]);
    }
}
