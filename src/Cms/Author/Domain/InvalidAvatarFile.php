<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\DomainError;

final class InvalidAvatarFile extends DomainError
{
    public static function notWebp(): self
    {
        return new self('The avatar must be a WebP image.', 'errors.author.avatar.not_webp', []);
    }

    public static function wrongSize(int $width, int $height): self
    {
        return new self(\sprintf('The avatar must be %d×%d pixels.', $width, $height), 'errors.author.avatar.wrong_size', ['%width%' => $width, '%height%' => $height]);
    }
}
