<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\Block;
use Shared\Domain\RichText\Callout;
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
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Turns rich text into the Twig components that paint it, one per block, to
 * set in a Prose column. Each module keeps its content as rich text and
 * leaves how it is painted to this adapter.
 */
final readonly class RichTextComponents
{
    /**
     * @return list<PageComponent>
     */
    public function __invoke(RichText $content): array
    {
        return array_map(self::block(...), $content->blocks());
    }

    private static function block(Block $block): PageComponent
    {
        return match (true) {
            $block instanceof Heading => new PageComponent('Shared:ContentHeading', [
                'level' => $block->level(),
                'text' => self::runs($block->text()),
            ]),
            $block instanceof Paragraph => new PageComponent('Shared:ContentParagraph', [
                'text' => self::runs($block->text()),
            ]),
            $block instanceof Quote => new PageComponent('Shared:ContentQuote', [
                'paragraphs' => array_map(self::runs(...), $block->paragraphs()),
            ]),
            $block instanceof ItemList => new PageComponent('Shared:ContentList', [
                'ordered' => ListStyle::Ordered === $block->style(),
                'items' => array_map(self::runs(...), $block->items()),
            ]),
            $block instanceof Callout => new PageComponent('Shared:ContentCallout', [
                'variant' => $block->variant()->value,
                'paragraphs' => array_map(self::runs(...), $block->paragraphs()),
            ]),
            $block instanceof Table => new PageComponent('Shared:ContentTable', [
                'header' => array_map(self::runs(...), $block->header()),
                'rows' => array_map(static fn (array $row): array => array_map(self::runs(...), $row), $block->rows()),
            ]),
            default => throw new \LogicException(\sprintf('No component paints %s blocks.', $block->type()->value)),
        };
    }

    /**
     * @return list<array{text: string, bold: bool}>
     */
    private static function runs(InlineText $text): array
    {
        return array_map(
            static fn (TextRun $run): array => ['text' => $run->text(), 'bold' => \in_array(Mark::Bold, $run->marks(), true)],
            $text->runs(),
        );
    }
}
