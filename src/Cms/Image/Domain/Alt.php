<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Alternative text of an image: what it shows, for those who can't see it,
 * and for search engines. A single line, with its whitespace collapsed to
 * single spaces and none around it.
 *
 * No standard sets a limit, but 125 characters is the length recommended for
 * screen readers to read it in one go, and search engines ask for it short.
 */
#[ORM\Embeddable]
final readonly class Alt
{
    public const int MAX_LENGTH = 125;

    #[ORM\Column(name: 'alt', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ('' === $this->value) {
            throw InvalidAlt::empty();
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidAlt::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
