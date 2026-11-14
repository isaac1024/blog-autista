<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Name an author signs with, as readers see it: a single line, with its
 * whitespace collapsed to single spaces and none around it. It needn't be
 * the name of anyone who signs in to the CMS.
 */
#[ORM\Embeddable]
final readonly class AuthorName
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 100;

    #[ORM\Column(name: 'name', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidAuthorName::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidAuthorName::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
