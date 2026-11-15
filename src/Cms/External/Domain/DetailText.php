<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Checks a text detail of an external resource: no longer than it may be,
 * and on a single line unless it is one that runs over several.
 */
final readonly class DetailText
{
    public static function line(string $detail, ?string $value, int $maxLength): ?string
    {
        if (null !== $value && 1 === preg_match('/[\r\n]/', $value)) {
            throw InvalidResourceDetails::singleLine($detail);
        }

        return self::lines($detail, null === $value ? null : (string) preg_replace('/\s+/u', ' ', $value), $maxLength);
    }

    public static function lines(string $detail, ?string $value, int $maxLength): ?string
    {
        if (null !== $value && mb_strlen($value) > $maxLength) {
            throw InvalidResourceDetails::tooLong($detail, $maxLength);
        }

        return $value;
    }
}
