<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Persistence\Doctrine;

use Cms\Author\Domain\AvatarPath;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps where the file of an avatar is kept to a string column, null for an
 * author without one.
 */
final class AvatarType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AvatarPath
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        return new AvatarPath($value);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof AvatarPath) {
            throw InvalidType::new($value, self::class, ['null', AvatarPath::class]);
        }

        return $value->value();
    }
}
