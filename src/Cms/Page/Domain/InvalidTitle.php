<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidTitle extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The page title must have at least %d characters.', $minLength));
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The page title must not exceed %d characters.', $maxLength));
    }
}
