<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\TagIds;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the tags of a post to a JSON column: their ids, in order.
 */
final class TagIdsType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): TagIds
    {
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['string']);
        }

        try {
            $ids = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValueNotConvertible::new($value, self::class, $exception->getMessage(), $exception);
        }

        $ids = self::ids($ids) ?? throw ValueNotConvertible::new($value, self::class, 'The stored tags are not a list of ids.');

        return TagIds::fromValues($ids);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof TagIds) {
            throw InvalidType::new($value, self::class, [TagIds::class]);
        }

        return json_encode($value->values(), \JSON_THROW_ON_ERROR);
    }

    /**
     * The ids decoded, null unless they are a list of strings.
     *
     * @return ?list<string>
     */
    public static function ids(mixed $decoded): ?array
    {
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        $ids = [];

        foreach ($decoded as $id) {
            if (!\is_string($id)) {
                return null;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
