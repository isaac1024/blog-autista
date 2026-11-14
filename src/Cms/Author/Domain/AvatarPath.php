<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Where the file of an avatar is kept, relative to the avatar storage. Each
 * avatar uploaded gets a name of its own, so the one being edited never
 * overwrites the one on the site.
 */
final readonly class AvatarPath
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[0-9a-f-]{36}\.webp$/';

    public function __construct(private string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the path of an avatar.', $value));
        }
    }

    public static function random(): self
    {
        return new self(AvatarName::random()->value().'.webp');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
