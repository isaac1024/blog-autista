<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

final class RedirectNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No redirect was found with id "%s".', $id));
    }
}
