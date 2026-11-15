<?php

declare(strict_types=1);

namespace Cms\External\Application;

final class ExternalResourceNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No external resource was found with id "%s".', $id));
    }
}
