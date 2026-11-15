<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\DomainError;

final class InvalidResourceTitle extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The title of an external resource must have at least %d characters.', $minLength), 'errors.external.title.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The title of an external resource must not exceed %d characters.', $maxLength), 'errors.external.title.too_long', ['%max%' => $maxLength]);
    }
}
