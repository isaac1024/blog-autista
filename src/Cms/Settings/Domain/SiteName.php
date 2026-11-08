<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Name of the blog, as the site shows it next to its logo and in the footer:
 * a single line, with its whitespace collapsed to single spaces and none
 * around it.
 */
#[ORM\Embeddable]
final readonly class SiteName
{
    public const int MIN_LENGTH = 2;
    public const int MAX_LENGTH = 60;

    #[ORM\Column(name: 'name', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidSiteName::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidSiteName::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
