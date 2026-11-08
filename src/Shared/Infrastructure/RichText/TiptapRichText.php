<?php

declare(strict_types=1);

namespace Shared\Infrastructure\RichText;

use Shared\Domain\RichText\Block;
use Shared\Domain\RichText\Callout;
use Shared\Domain\RichText\EmbeddedImage;
use Shared\Domain\RichText\Heading;
use Shared\Domain\RichText\InlineText;
use Shared\Domain\RichText\InvalidRichText;
use Shared\Domain\RichText\ItemList;
use Shared\Domain\RichText\ListStyle;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\Paragraph;
use Shared\Domain\RichText\Quote;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\Table;
use Shared\Domain\RichText\TextRun;
use Shared\Domain\RichText\Widget;

/**
 * Translates between the documents of the Tiptap editor (ProseMirror JSON)
 * and the stored rich text format, so nothing past this adapter knows Tiptap.
 *
 * It is strict: a node or mark it doesn't know is an error, never dropped, so
 * no content is lost on the way. Only empty blocks are left out, which the
 * editor leaves behind, e.g. the empty paragraph of a blank document.
 */
final readonly class TiptapRichText
{
    /**
     * @throws InvalidRichText when the document is not one the editor can produce
     *
     * @return array{version: int, blocks: list<array<string, mixed>>} the stored format
     */
    public function toStored(string $json): array
    {
        try {
            $document = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidRichText::malformed('document', 'valid JSON');
        }

        $document = self::node($document, 'doc', 'document');
        $blocks = [];

        foreach (self::content($document, 'document') as $index => $node) {
            $block = self::block($node, \sprintf('document.content[%d]', $index));

            if (null !== $block) {
                $blocks[] = $block;
            }
        }

        return ['version' => RichText::VERSION, 'blocks' => $blocks];
    }

    /**
     * @param array<array-key, mixed> $stored the stored format
     *
     * @return array<string, mixed> a Tiptap document
     */
    public function toTiptap(array $stored): array
    {
        $content = array_map(self::tiptapBlock(...), RichText::fromArray($stored)->blocks());

        // Tiptap documents always hold a block: a blank one has an empty paragraph.
        return ['type' => 'doc', 'content' => [] === $content ? [['type' => 'paragraph']] : $content];
    }

    /**
     * @return null|array<string, mixed>
     */
    private static function block(mixed $node, string $path): ?array
    {
        $type = self::type($node, $path);
        $node = self::node($node, $type, $path);
        $attrs = self::attrs($node, $path);

        return match ($type) {
            'paragraph' => self::nonEmpty(['type' => 'paragraph', 'text' => self::inline($node, $path)], 'text'),
            'heading' => self::nonEmpty(['type' => 'heading', 'level' => $attrs['level'] ?? null, 'text' => self::inline($node, $path)], 'text'),
            'blockquote' => self::quote($node, $path),
            'callout' => self::nonEmpty(['type' => 'callout', 'variant' => $attrs['variant'] ?? null, 'paragraphs' => self::paragraphs($node, $path)], 'paragraphs'),
            'bulletList' => self::nonEmpty(['type' => 'list', 'style' => ListStyle::Unordered->value, 'items' => self::items($node, $path)], 'items'),
            'orderedList' => self::nonEmpty(['type' => 'list', 'style' => ListStyle::Ordered->value, 'items' => self::items($node, $path)], 'items'),
            'table' => self::table($node, $path),
            // An image is embedded by its id alone; the editor shows the rest.
            'image' => ['type' => 'image', 'id' => $attrs['id'] ?? null],
            // So is the page of a widget.
            'widget' => ['type' => 'widget', 'kind' => $attrs['kind'] ?? null, 'page_id' => $attrs['id'] ?? null],
            default => throw InvalidRichText::malformed($path.'.type', 'a block the editor offers'),
        };
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return null|array<string, mixed>
     */
    private static function nonEmpty(array $block, string $key): ?array
    {
        return [] === $block[$key] ? null : $block;
    }

    /**
     * A quote ends with its source, when it has one. A source left empty is
     * none; a quote left with only its source is empty, so it is left out.
     *
     * @param array<array-key, mixed> $node
     *
     * @return null|array<string, mixed>
     */
    private static function quote(array $node, string $path): ?array
    {
        $content = self::content($node, $path);
        $last = array_key_last($content);
        $source = null;

        if (null !== $last && 'quoteSource' === self::type($content[$last], $sourcePath = \sprintf('%s.content[%d]', $path, $last))) {
            $source = self::plainText(self::node($content[$last], 'quoteSource', $sourcePath), $sourcePath);
            $node['content'] = \array_slice($content, 0, $last);
        }

        $quote = self::nonEmpty(['type' => 'quote', 'paragraphs' => self::paragraphs($node, $path)], 'paragraphs');

        if (null !== $quote && null !== $source && '' !== $source) {
            $quote['source'] = $source;
        }

        return $quote;
    }

    /**
     * Text of a node that takes no marks, trimmed.
     *
     * @param array<array-key, mixed> $node
     */
    private static function plainText(array $node, string $path): string
    {
        $text = '';

        foreach (self::content($node, $path) as $index => $child) {
            $childPath = \sprintf('%s.content[%d]', $path, $index);
            $child = self::node($child, 'text', $childPath);

            if ([] !== ($child['marks'] ?? [])) {
                throw InvalidRichText::malformed($childPath.'.marks', 'empty: this text takes no marks');
            }

            $text .= self::string($child['text'] ?? null, $childPath.'.text');
        }

        return trim($text);
    }

    /**
     * Paragraphs of a quote or a callout, without the empty ones.
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<list<array<string, mixed>>>
     */
    private static function paragraphs(array $node, string $path): array
    {
        $paragraphs = [];

        foreach (self::content($node, $path) as $index => $child) {
            $text = self::inline(self::node($child, 'paragraph', \sprintf('%s.content[%d]', $path, $index)), \sprintf('%s.content[%d]', $path, $index));

            if ([] !== $text) {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs;
    }

    /**
     * Items of a list, each a single paragraph, without the empty ones.
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<list<array<string, mixed>>>
     */
    private static function items(array $node, string $path): array
    {
        $items = [];

        foreach (self::content($node, $path) as $index => $child) {
            $itemPath = \sprintf('%s.content[%d]', $path, $index);
            $text = self::singleParagraph(self::node($child, 'listItem', $itemPath), $itemPath);

            if ([] !== $text) {
                $items[] = $text;
            }
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return null|array<string, mixed>
     */
    private static function table(array $node, string $path): ?array
    {
        $rows = [];

        foreach (self::content($node, $path) as $rowIndex => $row) {
            $rowPath = \sprintf('%s.content[%d]', $path, $rowIndex);
            // The first row is the header: it names every column.
            $cellType = 0 === $rowIndex ? 'tableHeader' : 'tableCell';
            $cells = [];

            foreach (self::content(self::node($row, 'tableRow', $rowPath), $rowPath) as $cellIndex => $cell) {
                $cellPath = \sprintf('%s.content[%d]', $rowPath, $cellIndex);
                $cell = self::node($cell, $cellType, $cellPath);
                $attrs = self::attrs($cell, $cellPath);

                if (1 !== ($attrs['colspan'] ?? 1) || 1 !== ($attrs['rowspan'] ?? 1)) {
                    throw InvalidRichText::malformed($cellPath, 'a cell that spans a single row and column');
                }

                $cells[] = self::singleParagraph($cell, $cellPath);
            }

            $rows[] = $cells;
        }

        if ([] === $rows) {
            return null;
        }

        $header = array_shift($rows);
        // Body rows left with no text at all, such as the blank ones of a new table, are left out.
        $rows = array_values(array_filter($rows, static fn (array $cells): bool => [] !== array_merge(...$cells)));

        return ['type' => 'table', 'header' => $header, 'rows' => $rows];
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private static function singleParagraph(array $node, string $path): array
    {
        $content = self::content($node, $path);

        if (1 !== \count($content)) {
            throw InvalidRichText::malformed($path.'.content', 'a single paragraph');
        }

        return self::inline(self::node($content[0], 'paragraph', $path.'.content[0]'), $path.'.content[0]');
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private static function inline(array $node, string $path): array
    {
        $runs = [];

        foreach (self::content($node, $path) as $index => $child) {
            $childPath = \sprintf('%s.content[%d]', $path, $index);
            $child = self::node($child, 'text', $childPath);
            $marks = [];
            $linked = false;
            $pageId = null;

            foreach (self::list($child['marks'] ?? [], $childPath.'.marks') as $markIndex => $mark) {
                $markPath = \sprintf('%s.marks[%d]', $childPath, $markIndex);
                $type = self::type($mark, $markPath);
                $marks[] = $type;

                if ('link' === $type) {
                    // A text links one page: a second link would hide which.
                    if ($linked) {
                        throw InvalidRichText::malformed($markPath, 'a single link');
                    }

                    $linked = true;
                    $pageId = self::attrs($mark, $markPath)['id'] ?? null;
                }
            }

            $run = [] === $marks ? ['text' => $child['text'] ?? null] : ['text' => $child['text'] ?? null, 'marks' => $marks];

            if ($linked) {
                $run['page_id'] = $pageId;
            }

            $runs[] = $run;
        }

        return $runs;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tiptapBlock(Block $block): array
    {
        return match (true) {
            $block instanceof Paragraph => self::tiptapParagraph($block->text()),
            $block instanceof Heading => ['type' => 'heading', 'attrs' => ['level' => $block->level()], 'content' => self::tiptapInline($block->text())],
            $block instanceof Quote => ['type' => 'blockquote', 'content' => [
                ...array_map(self::tiptapParagraph(...), $block->paragraphs()),
                ...(null === $block->source() ? [] : [['type' => 'quoteSource', 'content' => [['type' => 'text', 'text' => $block->source()]]]]),
            ]],
            $block instanceof Callout => ['type' => 'callout', 'attrs' => ['variant' => $block->variant()->value], 'content' => array_map(self::tiptapParagraph(...), $block->paragraphs())],
            $block instanceof ItemList => [
                'type' => ListStyle::Ordered === $block->style() ? 'orderedList' : 'bulletList',
                'content' => array_map(static fn (InlineText $item): array => ['type' => 'listItem', 'content' => [self::tiptapParagraph($item)]], $block->items()),
            ],
            $block instanceof EmbeddedImage => ['type' => 'image', 'attrs' => ['id' => $block->imageId()]],
            $block instanceof Widget => ['type' => 'widget', 'attrs' => ['kind' => $block->kind()->value, 'id' => $block->pageId()]],
            $block instanceof Table => ['type' => 'table', 'content' => [
                self::tiptapRow('tableHeader', $block->header()),
                ...array_map(static fn (array $row): array => self::tiptapRow('tableCell', $row), $block->rows()),
            ]],
            default => throw new \LogicException(\sprintf('The editor has no block for %s.', $block::class)),
        };
    }

    /**
     * @param list<InlineText> $cells
     *
     * @return array<string, mixed>
     */
    private static function tiptapRow(string $cellType, array $cells): array
    {
        return ['type' => 'tableRow', 'content' => array_map(
            static fn (InlineText $cell): array => ['type' => $cellType, 'content' => [self::tiptapParagraph($cell)]],
            $cells,
        )];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tiptapParagraph(InlineText $text): array
    {
        return $text->isEmpty() ? ['type' => 'paragraph'] : ['type' => 'paragraph', 'content' => self::tiptapInline($text)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tiptapInline(InlineText $text): array
    {
        return array_map(
            static function (TextRun $run): array {
                $node = ['type' => 'text', 'text' => $run->text()];

                foreach ($run->marks() as $mark) {
                    $node['marks'][] = Mark::Link === $mark
                        ? ['type' => $mark->value, 'attrs' => ['id' => $run->pageId()]]
                        : ['type' => $mark->value];
                }

                return $node;
            },
            $text->runs(),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function node(mixed $node, string $type, string $path): array
    {
        $actual = self::type($node, $path);

        if ($type !== $actual) {
            throw InvalidRichText::malformed($path.'.type', \sprintf('"%s"', $type));
        }

        return $node;
    }

    /**
     * @phpstan-assert array<array-key, mixed> $node
     */
    private static function type(mixed $node, string $path): string
    {
        if (!\is_array($node) || !\is_string($node['type'] ?? null)) {
            throw InvalidRichText::malformed($path, 'a node with a type');
        }

        return $node['type'];
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    private static function attrs(array $node, string $path): array
    {
        $attrs = $node['attrs'] ?? [];

        if (!\is_array($attrs)) {
            throw InvalidRichText::malformed($path.'.attrs', 'an object');
        }

        return $attrs;
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<mixed>
     */
    private static function content(array $node, string $path): array
    {
        return self::list($node['content'] ?? [], $path.'.content');
    }

    private static function string(mixed $value, string $path): string
    {
        if (!\is_string($value)) {
            throw InvalidRichText::malformed($path, 'a string');
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidRichText::malformed($path, 'a list');
        }

        return $value;
    }
}
