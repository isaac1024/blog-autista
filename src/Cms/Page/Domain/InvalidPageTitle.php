<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPageTitle extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The page title must have at least %d characters.', $minLength), 'errors.page.title.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The page title must not exceed %d characters.', $maxLength), 'errors.page.title.too_long', ['%max%' => $maxLength]);
    }
}
