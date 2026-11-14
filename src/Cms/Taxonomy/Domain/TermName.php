<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Name of a term, as readers see it: a single line, with its whitespace
 * collapsed to single spaces and none around it, short enough for a label.
 */
#[ORM\Embeddable]
final readonly class TermName
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 60;

    #[ORM\Column(name: 'name', length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidTermName::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidTermName::tooLong(self::MAX_LENGTH);
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
