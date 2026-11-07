<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\DomainError;

final class InvalidImageFile extends DomainError
{
    public static function missing(): self
    {
        return new self('An image needs its file.', 'errors.image.file.missing', []);
    }

    public static function notWebp(): self
    {
        return new self('The file of an image must be a WebP image.', 'errors.image.file.not_webp', []);
    }

    public static function wrongSize(int $width, int $height): self
    {
        return new self(\sprintf('The file of an image must be %d by %d pixels.', $width, $height), 'errors.image.file.wrong_size', ['%width%' => $width, '%height%' => $height]);
    }
}
