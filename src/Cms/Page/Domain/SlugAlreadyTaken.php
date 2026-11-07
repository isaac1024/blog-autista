<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class SlugAlreadyTaken extends DomainError
{
    public static function bySlug(Slug $slug, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Another page is already addressed by "%s".', $slug->value()), 'errors.page.slug.taken', ['%slug%' => $slug->value()], $previous);
    }
}
