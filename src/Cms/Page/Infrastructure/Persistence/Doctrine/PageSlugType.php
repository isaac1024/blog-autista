<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence\Doctrine;

use Cms\Page\Domain\HomeSlug;
use Cms\Page\Domain\PageSlug;
use Cms\Page\Domain\Slug;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the address of a page to a string column: the home page is stored with
 * an empty slug, every other page with its own.
 */
final class PageSlugType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PageSlug
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        return '' === $value ? new HomeSlug() : new Slug($value);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PageSlug) {
            throw InvalidType::new($value, self::class, ['null', PageSlug::class]);
        }

        return $value->value();
    }
}
