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
     * Words of Spanish so common that almost every post has them: every word
     * searched must be in a post, so one of these would only leave out the
     * few without it, and make the search read nearly all of them.
     */
    private const array COMMON = [
        'al', 'ante', 'aquí', 'así', 'aun', 'aún', 'cada', 'como', 'cómo', 'con', 'contra', 'cual', 'cuál', 'cuando', 'cuándo',
        'del', 'desde', 'donde', 'dónde', 'durante', 'ella', 'ellas', 'ellos', 'entre', 'era', 'eran', 'esa', 'esas', 'ese', 'eso', 'esos',
        'esta', 'está', 'estas', 'están', 'este', 'esto', 'estos', 'fue', 'hacia', 'han', 'has', 'hasta', 'hay', 'las', 'les', 'los',
        'más', 'mis', 'muy', 'nos', 'otra', 'otras', 'otro', 'otros', 'para', 'pero', 'por', 'porque', 'qué', 'que', 'quien', 'quién',
        'sea', 'según', 'ser', 'sin', 'sino', 'sobre', 'son', 'sus', 'también', 'tan', 'tiene', 'toda', 'todas', 'todo', 'todos',
        'tras', 'tus', 'una', 'unas', 'uno', 'unos', 'ya',
    ];

    /**
     * @param list<string> $words
     */
    private function __construct(public array $words) {}

    public static function fromText(string $text): self
    {
        preg_match_all(self::WORD, mb_strtolower($text), $matches);

        return new self(\array_slice(array_values(array_diff(array_unique($matches[0]), self::COMMON)), 0, self::MAX_WORDS));
    }

    public function isEmpty(): bool
    {
        return [] === $this->words;
    }
}
