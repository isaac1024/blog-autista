<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Where the copy the CMS keeps of the image of an external resource is,
 * relative to its storage. Each image read gets a name of its own, so the
 * one being edited never overwrites the one on the site.
 */
final readonly class ResourceImagePath
{
    private const string PATTERN = '/^[0-9a-f-]{36}\.webp$/';

    public function __construct(private string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the path of the image of an external resource.', $value));
        }
    }

    public static function random(): self
    {
        return new self(ResourceImageName::random()->value().'.webp');
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
