<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

/**
 * Adapts a free text to an SEO-friendly slug on demand: transliterated to
 * lowercase ASCII and without stop words, e.g. "Cómo organizar la semana"
 * becomes "como-organizar-semana".
 */
final readonly class Slugger
{
    /**
     * Spanish articles, prepositions and conjunctions that add
     * length but no meaning. Words that change the meaning ("sin", "no") are
     * deliberately kept.
     */
    private const array STOP_WORDS = [
        'a', 'al', 'ante', 'con', 'de', 'del', 'desde', 'e', 'el', 'en', 'entre',
        'hacia', 'la', 'las', 'lo', 'los', 'o', 'para', 'por', 'que', 'su', 'sus',
        'tras', 'u', 'un', 'una', 'unas', 'unos', 'y',
    ];

    public function __construct(private string $text) {}

    public function __invoke(): string
    {
        $words = $this->words();
        $meaningful = array_values(array_diff($words, self::STOP_WORDS));

        return implode('-', [] === $meaningful ? $words : $meaningful);
    }

    /**
     * @return list<string>
     */
    private function words(): array
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->text);

        $words = preg_split(
            '/[^a-z0-9]+/',
            strtolower(false === $ascii ? $this->text : $ascii),
            flags: \PREG_SPLIT_NO_EMPTY,
        );

        return false === $words ? [] : $words;
    }
}
