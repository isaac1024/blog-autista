<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

final class InvalidTermSlug extends DomainError
{
    public static function reserved(Slug $slug): self
    {
        return new self(\sprintf('The site serves something else under "/%s/", so no section takes that slug.', $slug->value()), 'errors.term.slug.reserved', ['%slug%' => $slug->value()]);
    }
}
