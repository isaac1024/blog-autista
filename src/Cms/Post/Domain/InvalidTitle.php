<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

final class InvalidTitle extends DomainError
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('The post title must have at least %d characters.', $minLength), 'errors.post.title.too_short', ['%min%' => $minLength]);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The post title must not exceed %d characters.', $maxLength), 'errors.post.title.too_long', ['%max%' => $maxLength]);
    }
}
