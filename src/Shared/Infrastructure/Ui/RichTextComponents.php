<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

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
use Shared\Domain\RichText\Widget;
use Shared\Domain\RichText\WidgetKind;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Turns rich text into the Twig components that paint it, one per block, to
 * set in a Prose column. Each module keeps its content as rich text and
 * leaves how it is painted to this adapter.
 */
final readonly class RichTextComponents
{
    /**
     * Embedded images and the pages of widgets are painted from what the
     * caller knows of them, keyed by id; one it doesn't give, such as one off
     * the site, isn't painted, and text linking it stays plain.
     *
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images
     * @param array<string, array{url: string, title: string}>                                                           $pages  published pages linked, with their current address, keyed by id
     *
     * @return list<PageComponent>
     */
    public function __invoke(RichText $content, array $images, array $pages): array
    {
        $links = array_map(static fn (array $page): string => $page['url'], $pages);
        $components = [];

        foreach ($content->blocks() as $block) {
            if ($block instanceof EmbeddedImage) {
                $image = $images[$block->imageId()] ?? null;

                if (null !== $image) {
                    $components[] = self::image($image, $links);
                }

                continue;
            }

            if ($block instanceof Widget) {
                $page = $pages[$block->pageId()] ?? null;

                if (null !== $page) {
                    $components[] = self::widget($block->kind(), $page);
                }

                continue;
            }

            $components[] = self::block($block, $links);
        }

        return $components;
    }

    /**
     * @param array{url: string, title: string} $page
     */
    private static function widget(WidgetKind $kind, array $page): PageComponent
    {
        return match ($kind) {
            WidgetKind::RelatedPage => new PageComponent('Shared:ContentRelatedPage', [
                'title' => $page['title'],
                'url' => $page['url'],
            ]),
        };
    }

    /**
     * @param array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText} $image
     * @param array<string, string>                                                                       $links
     */
    private static function image(array $image, array $links): PageComponent
    {
        $caption = $image['caption']->blocks()[0] ?? null;

        return new PageComponent('Shared:ContentImage', [
            'src' => $image['src'],
            'srcset' => $image['srcset'],
            'width' => $image['width'],
            'height' => $image['height'],
            'alt' => $image['alt'],
            // A caption is a single paragraph.
            'caption' => $caption instanceof Paragraph ? self::runs($caption->text(), $links) : [],
        ]);
    }

    /**
     * @param array<string, string> $links
     */
    private static function block(Block $block, array $links): PageComponent
    {
        $runs = static fn (InlineText $text): array => self::runs($text, $links);

        return match (true) {
            $block instanceof Heading => new PageComponent('Shared:ContentHeading', [
                'level' => $block->level(),
                'text' => $runs($block->text()),
            ]),
            $block instanceof Paragraph => new PageComponent('Shared:ContentParagraph', [
                'text' => $runs($block->text()),
            ]),
            $block instanceof Quote => new PageComponent('Shared:ContentQuote', [
                'paragraphs' => array_map($runs, $block->paragraphs()),
                'source' => $block->source(),
            ]),
            $block instanceof ItemList => new PageComponent('Shared:ContentList', [
                'ordered' => ListStyle::Ordered === $block->style(),
                'items' => array_map($runs, $block->items()),
            ]),
            $block instanceof Callout => new PageComponent('Shared:ContentCallout', [
                'variant' => $block->variant()->value,
                'paragraphs' => array_map($runs, $block->paragraphs()),
            ]),
            $block instanceof Table => new PageComponent('Shared:ContentTable', [
                'header' => array_map($runs, $block->header()),
                'rows' => array_map(static fn (array $row): array => array_map($runs, $row), $block->rows()),
            ]),
            default => throw new \LogicException(\sprintf('No component paints %s blocks.', $block->type()->value)),
        };
    }

    /**
     * The text in stretches, each linking one page or none: runs in a row
     * that link the same page form one stretch, so a link partly bold is
     * still one link. A run whose page isn't on the site links none.
     *
     * @param array<string, string> $links
     *
     * @return list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>
     */
    private static function runs(InlineText $text, array $links): array
    {
        $stretches = [];
        $last = null;

        foreach ($text->runs() as $run) {
            $href = null === $run->pageId() ? null : ($links[$run->pageId()] ?? null);
            $piece = ['text' => $run->text(), 'bold' => \in_array(Mark::Bold, $run->marks(), true)];

            if (null !== $last && $stretches[$last]['href'] === $href) {
                $stretches[$last]['runs'][] = $piece;

                continue;
            }

            $stretches[] = ['href' => $href, 'runs' => [$piece]];
            $last = array_key_last($stretches);
        }

        return $stretches;
    }
}
