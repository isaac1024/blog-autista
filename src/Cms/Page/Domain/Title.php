<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Human-readable title of a page, without surrounding whitespace.
 */
#[ORM\Embeddable]
final readonly class Title
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 100;

    #[ORM\Column(name: 'title', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidTitle::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidTitle::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
