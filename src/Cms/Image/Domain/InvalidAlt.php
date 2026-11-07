<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\DomainError;

final class InvalidAlt extends DomainError
{
    public static function empty(): self
    {
        return new self('The alternative text of an image must not be empty.', 'errors.image.alt.empty', []);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The alternative text of an image must not exceed %d characters.', $maxLength), 'errors.image.alt.too_long', ['%max%' => $maxLength]);
    }
}
