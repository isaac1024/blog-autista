<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

final class AuthorSlugAlreadyTaken extends DomainError
{
    public static function bySlug(Slug $slug, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Another author is already addressed by "%s".', $slug->value()), 'errors.author.slug.taken', ['%slug%' => $slug->value()], $previous);
    }
}
