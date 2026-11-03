<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * URL path segment of a page: lowercase ASCII letters, digits and single hyphens.
 */
final readonly class Slug implements PageSlug
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 80;

    /**
     * Shape of a valid slug without anchors, so forms can reuse it as an HTML pattern.
     */
    public const string SHAPE = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private const string PATTERN = '/^'.self::SHAPE.'$/';

    public function __construct(private string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidSlug::malformed($value);
        }

        if (\strlen($value) < self::MIN_LENGTH) {
            throw InvalidSlug::tooShort(self::MIN_LENGTH);
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidSlug::tooLong(self::MAX_LENGTH);
        }
    }

    #[\Override]
    public function value(): string
    {
        return $this->value;
    }
}
