<?php

declare(strict_types=1);

namespace Shared\Infrastructure\RichText;

use Shared\Domain\RichText\Block;
use Shared\Domain\RichText\Callout;
use Shared\Domain\RichText\EmbeddedImage;
use Shared\Domain\RichText\Heading;
use Shared\Domain\RichText\InlineText;
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
 * Writes the stored rich text format as a document of the Tiptap editor
 * (ProseMirror JSON).
 */
final readonly class TiptapDocumentWriter
{
    /**
     * @param array<array-key, mixed> $stored the stored format
     *
     * @return array<string, mixed> a Tiptap document
     */
    public function write(array $stored): array
    {
        $content = array_map(self::tiptapBlock(...), RichText::fromArray($stored)->blocks());

        // Tiptap documents always hold a block: a blank one has an empty paragraph.
        return ['type' => 'doc', 'content' => [] === $content ? [['type' => 'paragraph']] : $content];
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
            $block instanceof Widget => ['type' => 'widget', 'attrs' => ['kind' => $block->kind()->value, 'type' => $block->target()->type()->value, 'id' => $block->target()->id()]],
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
                        ? ['type' => $mark->value, 'attrs' => ['type' => $run->link()?->type()->value, 'id' => $run->link()?->id()]]
                        : ['type' => $mark->value];
                }

                return $node;
            },
            $text->runs(),
        );
    }
}
