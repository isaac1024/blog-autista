<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence\Doctrine;

use Cms\Settings\Domain\SettingsVersion;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the version of the settings last published to a JSON column, null
 * while they have never been published.
 */
final class SettingsVersionType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SettingsVersion
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        try {
            $data = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValueNotConvertible::new($value, self::class, $exception->getMessage(), $exception);
        }

        if (!\is_array($data) || !\is_string($data['name'] ?? null)) {
            throw ValueNotConvertible::new($value, self::class, 'The stored settings version has no "name".');
        }

        $defaultImage = $data['default_image_id'] ?? null;
        // Settings published before they had an author have none.
        $author = $data['author_id'] ?? null;
        $mainMenu = $data['main_menu'] ?? null;
        $footerMenu = $data['footer_menu'] ?? null;

        return SettingsVersion::fromPrimitives(
            $data['name'],
            \is_string($defaultImage) ? $defaultImage : '',
            \is_string($author) ? $author : '',
            \is_array($mainMenu) ? $mainMenu : [],
            \is_array($footerMenu) ? $footerMenu : [],
        );
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof SettingsVersion) {
            throw InvalidType::new($value, self::class, ['null', SettingsVersion::class]);
        }

        return json_encode([
            'name' => $value->name()->value(),
            'default_image_id' => $value->defaultImage()?->value(),
            'author_id' => $value->author()?->value(),
            'main_menu' => $value->mainMenu()->toArray(),
            'footer_menu' => $value->footerMenu()->toArray(),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
