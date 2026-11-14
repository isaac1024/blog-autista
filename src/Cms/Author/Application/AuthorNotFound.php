<?php

declare(strict_types=1);

namespace Cms\Author\Application;

final class AuthorNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No author was found with id "%s".', $id));
    }
}
