<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Shared\Domain\DomainError;

final class TermNotFound extends DomainError
{
    public static function withSlug(string $taxonomy, string $slug): self
    {
        return new self(\sprintf('No term of the "%s" taxonomy on the site is at "%s".', $taxonomy, $slug), 'errors.term.not_found', ['%slug%' => $slug]);
    }
}
