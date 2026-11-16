<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

/**
 * The image a post opens with is one the library hasn't.
 */
final class InvalidOpeningImage extends DomainError
{
    public static function unknown(ImageId $id): self
    {
        return new self(\sprintf('There is no image with id "%s".', $id->value()), 'errors.post.opening_image.unknown', []);
    }
}
