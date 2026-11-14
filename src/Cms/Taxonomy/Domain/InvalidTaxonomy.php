<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\DomainError;

final class InvalidTaxonomy extends DomainError
{
    public static function unknown(string $value): self
    {
        return new self(\sprintf('"%s" is not a taxonomy.', $value), 'errors.term.taxonomy.unknown', ['%taxonomy%' => $value]);
    }
}
