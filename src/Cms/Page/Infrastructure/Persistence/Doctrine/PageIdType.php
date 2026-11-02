<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence\Doctrine;

use Cms\Page\Domain\PageId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the PageId value object to a string column, so it can be the identifier.
 */
final class PageIdType extends Type
{
    public const string NAME = 'cms_page_id';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PageId
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return new PageId($value);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PageId) {
            throw InvalidType::new($value, self::NAME, ['null', PageId::class]);
        }

        return $value->value();
    }
}
