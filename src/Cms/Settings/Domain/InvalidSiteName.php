<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\DomainError;

final class InvalidSiteName extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The name of the blog must have at least %d characters.', $minLength), 'errors.settings.name.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The name of the blog must not exceed %d characters.', $maxLength), 'errors.settings.name.too_long', ['%max%' => $maxLength]);
    }
}
