<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use Shared\Domain\Uuid;

/**
 * Stores a Uuid value object in MariaDB's native UUID column: 16 bytes instead
 * of a 36-character string, validated by the database itself, and still read
 * and written as the canonical text, so the value objects don't change.
 * MariaDB only reorders version 1 UUIDs internally, so time-ordered version 7
 * identifiers keep sorting by creation.
 *
 * Each context extends it for its own identifier and registers it under its
 * own name in doctrine.yaml.
 *
 * @template T of Uuid
 */
abstract class UuidType extends Type
{
    #[\Override]
    final public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    /**
     * @return null|T
     */
    #[\Override]
    final public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string']);
        }

        return new ($this->uuidClass())($value);
    }

    #[\Override]
    final public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $class = $this->uuidClass();

        if (!$value instanceof $class) {
            throw InvalidType::new($value, static::class, ['null', $class]);
        }

        return $value->value();
    }

    /**
     * @return class-string<T>
     */
    abstract protected function uuidClass(): string;
}
