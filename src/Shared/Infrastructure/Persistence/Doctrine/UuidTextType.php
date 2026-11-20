<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the id of another resource, which the domain keeps as its
 * canonical text, in MariaDB's native UUID column, as UuidType does the id
 * of an aggregate: 16 bytes instead of a 36-character string in every key
 * that holds it, validated by the database, and compared with the UUID ids
 * it names without converting either side. Registered as "uuid_text" in
 * doctrine.yaml.
 */
final class UuidTextType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || \is_string($value)) {
            return $value;
        }

        throw InvalidType::new($value, self::class, ['null', 'string']);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || \is_string($value)) {
            return $value;
        }

        throw InvalidType::new($value, self::class, ['null', 'string']);
    }
}
