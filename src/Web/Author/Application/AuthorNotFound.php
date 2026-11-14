<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Shared\Domain\DomainError;

final class AuthorNotFound extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('No author on the site is at "%s".', $slug), 'errors.author.not_found', ['%slug%' => $slug]);
    }
}
