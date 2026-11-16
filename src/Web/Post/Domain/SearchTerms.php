<?php

declare(strict_types=1);

namespace Web\Post\Domain;

/**
 * The words a reader searches the posts by: runs of letters and digits at
 * least three long, each once, lowercase, as many as a search takes. A
 * shorter word finds nothing, as the index of the site never keeps one, so
 * it is left out; text with no word at all asks for nothing.
 */
final readonly class SearchTerms
{
    public const int MIN_LENGTH = 3;

    public const int MAX_WORDS = 10;

    private const string WORD = '/[\p{L}\p{N}]{3,}/u';

    /**
     * @param list<string> $words
     */
    private function __construct(public array $words) {}

    public static function fromText(string $text): self
    {
        preg_match_all(self::WORD, mb_strtolower($text), $matches);

        return new self(\array_slice(array_values(array_unique($matches[0])), 0, self::MAX_WORDS));
    }

    public function isEmpty(): bool
    {
        return [] === $this->words;
    }
}
