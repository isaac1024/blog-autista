<?php

declare(strict_types=1);

namespace Shared\Domain;

final class InvalidUuid extends DomainError
{
    public static function withValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid UUID.', $value), 'errors.uuid.invalid', ['%value%' => $value]);
    }
}
