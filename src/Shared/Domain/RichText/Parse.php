<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Checks the shape of decoded rich text data, naming the path of whatever is
 * wrong so the error points at it.
 *
 * @internal
 */
final class Parse
{
    /**
     * @return array<array-key, mixed>
     */
    public static function object(mixed $value, string $path): array
    {
        if (!\is_array($value) || array_is_list($value) && [] !== $value) {
            throw InvalidRichText::malformed($path, 'an object');
        }

        /* @var array<string, mixed> $object */
        return $value;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidRichText::malformed($path, 'a list');
        }

        return $value;
    }

    /**
     * @return non-empty-list<mixed>
     */
    public static function nonEmptyList(mixed $value, string $path): array
    {
        $list = self::list($value, $path);

        if ([] === $list) {
            throw InvalidRichText::empty($path);
        }

        return $list;
    }

    public static function string(mixed $value, string $path): string
    {
        if (!\is_string($value)) {
            throw InvalidRichText::malformed($path, 'a string');
        }

        return $value;
    }

    public static function int(mixed $value, string $path): int
    {
        if (!\is_int($value)) {
            throw InvalidRichText::malformed($path, 'an integer');
        }

        return $value;
    }

    /**
     * @param list<InlineText> $paragraphs
     */
    public static function assertParagraphs(array $paragraphs, string $path): void
    {
        if ([] === $paragraphs) {
            throw InvalidRichText::empty($path);
        }

        foreach ($paragraphs as $index => $paragraph) {
            if ($paragraph->isEmpty()) {
                throw InvalidRichText::empty(\sprintf('%s[%d]', $path, $index));
            }
        }
    }

    /**
     * @return list<InlineText>
     */
    public static function paragraphs(mixed $value, string $path): array
    {
        $paragraphs = [];

        foreach (self::nonEmptyList($value, $path) as $index => $paragraph) {
            $paragraphs[] = InlineText::nonEmptyFromArray($paragraph, \sprintf('%s[%d]', $path, $index));
        }

        return $paragraphs;
    }
}
