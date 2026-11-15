<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Persistence\Doctrine;

use Cms\External\Domain\Language;
use Cms\External\Domain\ResourceDetails;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceTitle;
use Cms\External\Domain\ResourceUrl;
use Cms\External\Domain\ResourceVersion;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Shared\Domain\External\ResourceKind;

/**
 * Stores a version of an external resource in a JSON column, with the kind
 * its details are of. It is only read and written whole, so it needs no
 * columns of its own.
 */
final class ResourceVersionType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ResourceVersion
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        $data = self::decode($value);
        $locator = $data['locator'] ?? null;
        $summary = $data['summary'] ?? null;
        $image = $data['image'] ?? null;
        $kind = ResourceKind::tryFrom(self::string($data, 'kind', $value)) ?? throw ValueNotConvertible::new($value, self::class, 'The stored resource version has an unknown kind.');
        $language = Language::tryFrom(self::string($data, 'language', $value)) ?? throw ValueNotConvertible::new($value, self::class, 'The stored resource version has an unknown language.');

        return new ResourceVersion(
            new ResourceUrl(self::string($data, 'url', $value)),
            \is_string($locator) ? $locator : null,
            new ResourceTitle(self::string($data, 'title', $value)),
            \is_string($summary) ? $summary : null,
            $language,
            ResourceDetails::fromPrimitives($kind, \is_array($data['details'] ?? null) ? $data['details'] : []),
            \is_string($image) ? new ResourceImagePath($image) : null,
        );
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ResourceVersion) {
            throw InvalidType::new($value, self::class, ['null', ResourceVersion::class]);
        }

        return json_encode([
            'kind' => $value->details()->kind()->value,
            'url' => $value->url()->value(),
            'locator' => $value->locator(),
            'title' => $value->title()->value(),
            'summary' => $value->summary(),
            'language' => $value->language()->value,
            'details' => $value->details()->toArray(),
            'image' => $value->image()?->value(),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(string $value): array
    {
        try {
            $data = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValueNotConvertible::new($value, self::class, $exception->getMessage(), $exception);
        }

        if (!\is_array($data)) {
            throw ValueNotConvertible::new($value, self::class, 'The stored resource version is not a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key, string $value): string
    {
        if (!\is_string($data[$key] ?? null)) {
            throw ValueNotConvertible::new($value, self::class, \sprintf('The stored resource version has no "%s".', $key));
        }

        return $data[$key];
    }
}
