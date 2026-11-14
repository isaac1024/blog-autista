<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Shared\Domain\DomainError;

final class SlugTaken extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('Another term of the taxonomy on the site is still at "%s".', $slug), 'errors.term.slug.taken', ['%slug%' => $slug]);
    }
}
