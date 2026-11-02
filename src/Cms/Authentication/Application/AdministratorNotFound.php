<?php

declare(strict_types=1);

namespace Cms\Authentication\Application;

final class AdministratorNotFound extends \RuntimeException
{
    public static function withIdentifier(string $identifier): self
    {
        return new self(\sprintf('No administrator was found with identifier "%s".', $identifier));
    }
}
