<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Persistence\Doctrine;

use Cms\Image\Domain\Alt;
use Cms\Image\Domain\ImageVersion;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Shared\Domain\RichText\RichText;

/**
 * Maps the version of an image last published to a JSON column, null while
 * it has never been published.
 */
final class ImageVersionType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ImageVersion
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

        if (!\is_array($data) || !\is_string($data['alt'] ?? null)) {
            throw ValueNotConvertible::new($value, self::class, 'The stored image version has no "alt".');
        }

        $caption = $data['caption'] ?? null;

        return new ImageVersion(new Alt($data['alt']), \is_array($caption) ? RichText::fromArray($caption) : null);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ImageVersion) {
            throw InvalidType::new($value, self::class, ['null', ImageVersion::class]);
        }

        return json_encode([
            'alt' => $value->alt()->value(),
            'caption' => $value->caption()?->toArray(),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
