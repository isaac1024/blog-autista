<?php

declare(strict_types=1);

namespace Web\Post\Application;

final class PostNotFound extends \RuntimeException
{
    public static function atAddress(string $address): self
    {
        return new self(\sprintf('No post on the site is at "%s".', $address));
    }
}
