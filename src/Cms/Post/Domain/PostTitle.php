<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Human-readable title of a post: a single line, with its whitespace
 * collapsed to single spaces and none around it.
 */
#[ORM\Embeddable]
final readonly class PostTitle
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 100;

    #[ORM\Column(name: 'title', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidPostTitle::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidPostTitle::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
