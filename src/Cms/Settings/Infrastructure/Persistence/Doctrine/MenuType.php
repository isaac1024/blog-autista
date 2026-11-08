<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence\Doctrine;

use Cms\Settings\Domain\Menu;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Maps a menu to a JSON column: its entries in order, each with the id of its
 * page and its label, null for none.
 */
final class MenuType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Menu
    {
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['string']);
        }

        try {
            $items = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValueNotConvertible::new($value, self::class, $exception->getMessage(), $exception);
        }

        if (!\is_array($items)) {
            throw ValueNotConvertible::new($value, self::class, 'The stored menu is not a list.');
        }

        return Menu::fromArray($items);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof Menu) {
            throw InvalidType::new($value, self::class, [Menu::class]);
        }

        return json_encode($value->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
