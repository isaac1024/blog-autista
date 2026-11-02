<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * URL path segment of a page: lowercase ASCII letters, digits and single hyphens.
 */
#[ORM\Embeddable]
final readonly class Slug
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 80;

    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        #[ORM\Column(name: 'slug', length: self::MAX_LENGTH)]
        private string $value,
    ) {
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
}
