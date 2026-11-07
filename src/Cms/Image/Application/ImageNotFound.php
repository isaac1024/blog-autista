<?php

declare(strict_types=1);

namespace Cms\Image\Application;

final class ImageNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No image was found with id "%s".', $id));
    }
}
