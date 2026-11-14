<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

final class TermSlugAlreadyTaken extends DomainError
{
    public static function bySlug(Slug $slug, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Another term of the taxonomy is already addressed by "%s".', $slug->value()), 'errors.term.slug.taken', ['%slug%' => $slug->value()], $previous);
    }
}
