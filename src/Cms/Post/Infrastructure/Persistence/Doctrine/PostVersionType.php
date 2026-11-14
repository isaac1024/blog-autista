<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\Classification;
use Cms\Post\Domain\PostVersion;
use Cms\Post\Domain\Title;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Shared\Domain\RichText\RichText;

/**
 * Stores the version of a post that is on the site in a JSON column. It is
 * only read and written whole, so it needs no columns of its own.
 */
final class PostVersionType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * Every field is optional but the title and the robots directive, so
     * each is read on its own.
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PostVersion
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        $data = self::decode($value);
        $slug = $data['slug'] ?? null;
        $section = $data['section_id'] ?? null;
        $author = $data['author_id'] ?? null;
        $tags = TagIdsType::ids($data['tag_ids'] ?? [])
            ?? throw ValueNotConvertible::new($value, self::class, 'The stored post version has tags that are not a list of ids.');
        $description = $data['meta_description'] ?? null;

        return new PostVersion(
            new Title(self::string($data, 'title', $value)),
            \is_string($slug) ? new Slug($slug) : null,
            Classification::fromPrimitives(\is_string($section) ? $section : '', \is_string($author) ? $author : '', $tags),
            RichText::fromArray(\is_array($data['content'] ?? null) ? $data['content'] : []),
            new Seo(\is_string($description) ? $description : null, Robots::from(self::string($data, 'robots', $value))),
        );
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PostVersion) {
            throw InvalidType::new($value, self::class, ['null', PostVersion::class]);
        }

        $classification = $value->classification();

        return json_encode([
            'title' => $value->title()->value(),
            'slug' => $value->slug()?->value(),
            'section_id' => $classification->section()?->value(),
            'author_id' => $classification->author()?->value(),
            'tag_ids' => $classification->tags()->values(),
            'content' => $value->content()->toArray(),
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
            throw ValueNotConvertible::new($value, self::class, 'The stored post version is not a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key, string $value): string
    {
        if (!\is_string($data[$key] ?? null)) {
            throw ValueNotConvertible::new($value, self::class, \sprintf('The stored post version has no "%s".', $key));
        }

        return $data[$key];
    }
}
