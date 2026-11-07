<?php

declare(strict_types=1);

namespace Shared\Tests\Domain\RichText;

/**
 * Builds rich text in its stored format for tests, block by block:
 *
 *     RichTextMother::stored(RichTextMother::heading(2, 'Qué cookies usamos'), RichTextMother::paragraph('Solo técnicas.'))
 */
final class RichTextMother
{
    public const array EMPTY = ['version' => 1, 'blocks' => []];

    /**
     * @param array<string, mixed> ...$blocks
     *
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public static function stored(array ...$blocks): array
    {
        return ['version' => 1, 'blocks' => array_values($blocks)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function heading(int $level, string $text): array
    {
        return ['type' => 'heading', 'level' => $level, 'text' => self::text($text)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paragraph(string ...$runs): array
    {
        return ['type' => 'paragraph', 'text' => self::text(...$runs)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function callout(string $variant, string $text): array
    {
        return ['type' => 'callout', 'variant' => $variant, 'paragraphs' => [self::text($text)]];
    }

    /**
     * @param list<string>       $header
     * @param list<list<string>> $rows
     *
     * @return array<string, mixed>
     */
    public static function table(array $header, array $rows): array
    {
        return ['type' => 'table', 'header' => self::cells($header), 'rows' => array_map(self::cells(...), $rows)];
    }

    /**
     * Runs of text; a run written as "**text**" is bold.
     *
     * @return list<array{text: string, marks?: list<string>}>
     */
    public static function text(string ...$runs): array
    {
        return array_map(
            static fn (string $run): array => str_starts_with($run, '**') && str_ends_with($run, '**')
                ? ['text' => substr($run, 2, -2), 'marks' => ['bold']]
                : ['text' => $run],
            array_values($runs),
        );
    }

    /**
     * @param list<string> $row
     *
     * @return list<list<array{text: string, marks?: list<string>}>>
     */
    private static function cells(array $row): array
    {
        return array_map(static fn (string $cell): array => '' === $cell ? [] : self::text($cell), $row);
    }
}
