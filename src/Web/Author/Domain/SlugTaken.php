<?php

declare(strict_types=1);

namespace Web\Author\Domain;

use Shared\Domain\DomainError;

final class SlugTaken extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('Another author on the site is still at "%s".', $slug), 'errors.author.slug.taken', ['%slug%' => $slug]);
    }
}
