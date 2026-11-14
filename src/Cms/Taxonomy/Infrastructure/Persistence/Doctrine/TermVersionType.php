<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Persistence\Doctrine;

use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\TermName;
use Cms\Taxonomy\Domain\TermVersion;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the version of a term that is on the site in a JSON column. It is
 * only read and written whole, so it needs no columns of its own.
 */
final class TermVersionType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TermVersion
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        $data = self::decode($value);
        $slug = $data['slug'] ?? null;
        $description = $data['meta_description'] ?? null;

        return new TermVersion(
            new TermName(self::string($data, 'name', $value)),
            \is_string($slug) ? new Slug($slug) : null,
            new Seo(\is_string($description) ? $description : null, Robots::from(self::string($data, 'robots', $value))),
        );
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof TermVersion) {
            throw InvalidType::new($value, self::class, ['null', TermVersion::class]);
        }

        return json_encode([
            'name' => $value->name()->value(),
            'slug' => $value->slug()?->value(),
            'meta_description' => $value->seo()->description(),
            'robots' => $value->seo()->robots()->value,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
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
            throw ValueNotConvertible::new($value, self::class, 'The stored term version is not a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key, string $value): string
    {
        if (!\is_string($data[$key] ?? null)) {
            throw ValueNotConvertible::new($value, self::class, \sprintf('The stored term version has no "%s".', $key));
        }

        return $data[$key];
    }
}
