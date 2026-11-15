<?php

declare(strict_types=1);

namespace Shared\Domain;

use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Base value object for identifiers expressed as an RFC 4122 UUID, kept in
 * lowercase as the database gives them back, however they were written.
 *
 * Every aggregate, and every module that refers to one, has an identifier of
 * its own, so they grow in number with the domain rather than with how it is
 * designed.
 *
 * @SuppressWarnings("PHPMD.NumberOfChildren")
 */
abstract readonly class Uuid implements \Stringable
{
    private string $value;

    final public function __construct(string $value)
    {
        if (!SymfonyUuid::isValid($value)) {
            throw InvalidUuid::withValue($value);
        }

        $this->value = strtolower($value);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Generates a new time-ordered (v7) UUID, which keeps database indexes compact.
     */
    public static function random(): static
    {
        return new static(SymfonyUuid::v7()->toRfc4122());
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Identifiers of different kinds are never equal, even with the same value.
     */
    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }
}
