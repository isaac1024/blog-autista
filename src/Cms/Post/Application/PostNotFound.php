<?php

declare(strict_types=1);

namespace Cms\Post\Application;

final class PostNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No post was found with id "%s".', $id));
    }
}
