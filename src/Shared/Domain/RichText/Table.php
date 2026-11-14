<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Table with a header row naming every column. Body cells may be empty,
 * header cells may not: they label the column for screen readers.
 */
final readonly class Table implements Block
{
    /**
     * @param list<InlineText>       $header
     * @param list<list<InlineText>> $rows
     */
    public function __construct(
        private array $header,
        private array $rows,
    ) {
        Parse::assertParagraphs($header, 'table.header');

        if ([] === $rows) {
            throw InvalidRichText::empty('table.rows');
        }

        foreach ($rows as $row) {
            if (\count($row) !== \count($header)) {
                throw InvalidRichText::unevenTable('table.rows', \count($header));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $rows = [];

        foreach (Parse::nonEmptyList($data['rows'] ?? null, $path.'.rows') as $rowIndex => $row) {
            $cells = [];

            foreach (Parse::nonEmptyList($row, \sprintf('%s.rows[%d]', $path, $rowIndex)) as $cellIndex => $cell) {
                $cells[] = InlineText::fromArray($cell, \sprintf('%s.rows[%d][%d]', $path, $rowIndex, $cellIndex));
            }

            $rows[] = $cells;
        }

        return new self(Parse::paragraphs($data['header'] ?? null, $path.'.header'), $rows);
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Table;
    }

    /**
     * @return list<InlineText>
     */
    public function header(): array
    {
        return $this->header;
    }

    /**
     * @return list<list<InlineText>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    #[\Override]
    public function texts(): array
    {
        return array_merge($this->header, ...$this->rows);
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'type' => $this->type()->value,
            'header' => self::cellsToArray($this->header),
            'rows' => array_map(self::cellsToArray(...), $this->rows),
        ];
    }

    /**
     * @param list<InlineText> $cells
     *
     * @return list<list<array{text: string, marks?: list<string>, page_id?: string, post_id?: string, term_id?: string, author_id?: string}>>
     */
    private static function cellsToArray(array $cells): array
    {
        return array_map(static fn (InlineText $text): array => $text->toArray(), $cells);
    }
}
