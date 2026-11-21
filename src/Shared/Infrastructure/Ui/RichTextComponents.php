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
use Shared\Domain\RichText\Mosaic;
use Shared\Domain\RichText\Paragraph;
use Shared\Domain\RichText\Quote;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\Table;
use Shared\Domain\RichText\Widget;
use Shared\Domain\RichText\WidgetKind;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Turns rich text into the Twig components that paint it, one per block, to
 * set in a Prose column. Each module keeps its content as rich text and
 * leaves how it is painted to this adapter.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type PostCard from PostCards
 *
 * @phpstan-type RelatedView array{kind: string, summary: ?string, image: ?array{src: string, srcset: string, width: int, height: int}, section: ?string, published: ?\DateTimeImmutable}
 * @phpstan-type LinkedContent array{url: string, title: string, external?: ExternalView, related?: RelatedView}
 */
final readonly class RichTextComponents
{
    /**
     * An email address as people write one, such as nombre.apellido@dominio.es:
     * a name, an at sign and a domain that ends in letters, so the full stop
     * that ends a sentence after it is left out. Captured, to cut text around it.
     */
    private const string EMAIL = '/((?<![\p{L}\p{N}._%+-])[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}(?![\p{L}\p{N}-]))/u';

    /**
     * Embedded images and what widgets show are painted from what the caller
     * knows of them, keyed by id; one it doesn't give, such as one off the
     * site, isn't painted, and text linking it stays plain. Ids are never
     * shared, so pages, posts, terms and authors share one map.
     *
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images
     * @param array<string, LinkedContent>                                                                               $pages   the contents linked on the site, pages or posts, sections, tags, authors or external resources, with their current address and name, and all a widget paints of an external resource, keyed by id
     * @param array<string, list<PostCard>>                                                                              $mosaics the cards of the posts each mosaic shows, keyed by Mosaic::key(); a mosaic with none isn't painted
     *
     * @return list<PageComponent>
     */
    public function __invoke(RichText $content, array $images, array $pages, array $mosaics): array
    {
        $links = array_map(static fn (array $page): string => $page['url'], $pages);
        $components = [];
        $anchors = HeadingAnchors::byBlock($content);
        // The titles of the cards of a mosaic are headings one level below
        // the last one before it, as the outline of the page goes.
        $headingLevel = 1;

        foreach ($content->blocks() as $index => $block) {
            if ($block instanceof Heading) {
                $headingLevel = $block->level();
            }

            if ($block instanceof Mosaic) {
                $cards = $mosaics[$block->key()] ?? [];

                if ([] !== $cards) {
                    $components[] = new PageComponent('Shared:PostCards', ['cards' => $cards, 'headingLevel' => $headingLevel + 1, 'wide' => false, 'lead' => false]);
                }

                continue;
            }

            if ($block instanceof EmbeddedImage) {
                $image = $images[$block->imageId()] ?? null;

                if (null !== $image) {
                    $components[] = self::image($image, $links, false, false);
                }

                continue;
            }

            if ($block instanceof Widget) {
                $page = $pages[$block->target()->id()] ?? null;

                if (null !== $page) {
                    $components[] = self::widget($block->kind(), $page, false);
                }

                continue;
            }

            $components[] = self::block($block, $links, $anchors[$index] ?? null);
        }

        return self::leading($components);
    }

    /**
     * The image a content opens with, apart from its text, with its caption
     * linking what is on the site, as an embedded image's does.
     *
     * @param array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText} $image
     * @param array<string, LinkedContent>                                                                $pages as for its text
     */
    public function opening(array $image, array $pages): PageComponent
    {
        return self::image($image, array_map(static fn (array $page): string => $page['url'], $pages), true, false);
    }

    /**
     * The first picture of the text is fetched at once: it may be the
     * largest thing on the page as it opens. One further down costs no more
     * than the fetch it would have had anyway.
     *
     * @param list<PageComponent> $components
     *
     * @return list<PageComponent>
     */
    private static function leading(array $components): array
    {
        foreach ($components as $index => $component) {
            $cards = $component->props['cards'] ?? null;
            $picture = match ($component->name) {
                'Shared:ContentImage' => true,
                // The first card is the one fetched at once.
                'Shared:PostCards' => \is_array($cards) && \is_array($cards[0] ?? null) && null !== ($cards[0]['image'] ?? null),
                default => null !== ($component->props['image'] ?? null),
            };

            if ($picture) {
                $components[$index] = new PageComponent($component->name, [...$component->props, 'lead' => true], $component->children);

                return $components;
            }
        }

        return $components;
    }

    /**
     * An external resource is painted as what it is: behind its facade, or
     * as a card that leads to it.
     *
     * @param LinkedContent $page
     * @param bool          $lead whether its picture, if any, is the first of the text
     */
    private static function widget(WidgetKind $kind, array $page, bool $lead): PageComponent
    {
        if (isset($page['external'])) {
            return ExternalComponents::component($page['external'], $lead);
        }

        $related = $page['related'] ?? ['kind' => 'page', 'summary' => null, 'image' => null, 'section' => null, 'published' => null];

        return match ($kind) {
            WidgetKind::RelatedPage => new PageComponent('Shared:ContentRelated', [
                'kind' => $related['kind'],
                'url' => $page['url'],
                'title' => $page['title'],
                'summary' => $related['summary'],
                'image' => $related['image'],
                'section' => $related['section'],
                'published' => $related['published'],
                'lead' => false,
            ]),
        };
    }

    /**
     * @param array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText} $image
     * @param array<string, string>                                                                       $links
     * @param bool                                                                                        $opening whether it opens the content rather than sits in it
     * @param bool                                                                                        $lead    whether it is the first picture of the text
     */
    private static function image(array $image, array $links, bool $opening, bool $lead): PageComponent
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
            'opening' => $opening,
            'lead' => $lead,
        ]);
    }

    /**
     * @param array<string, string> $links
     */
    private static function block(Block $block, array $links, ?string $anchor): PageComponent
    {
        $runs = static fn (InlineText $text): array => self::runs($text, $links);

        return match (true) {
            $block instanceof Heading => new PageComponent('Shared:ContentHeading', [
                'level' => $block->level(),
                'text' => $runs($block->text()),
                'anchor' => $anchor,
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
     * The text in stretches, each linking one content or none: runs in a row
     * that link the same one form one stretch, so a link partly bold is
     * still one link. A run whose content isn't on the site links none.
     *
     * An email address written in a run that links nothing links itself, so
     * a reader writes to it with a click, as written: its address stays in
     * sight, to copy.
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
            $linked = $run->link()?->id();
            $bold = \in_array(Mark::Bold, $run->marks(), true);
            $pieces = null === $linked
                ? self::emailPieces($run->text())
                : [[$links[$linked] ?? null, $run->text()]];

            foreach ($pieces as [$href, $piece]) {
                $piece = ['text' => $piece, 'bold' => $bold];

                if (null !== $last && $stretches[$last]['href'] === $href) {
                    $stretches[$last]['runs'][] = $piece;

                    continue;
                }

                $stretches[] = ['href' => $href, 'runs' => [$piece]];
                $last = array_key_last($stretches);
            }
        }

        return $stretches;
    }

    /**
     * The text of a run cut around the email addresses written in it, each
     * piece with what it links: an address, itself; the rest, nothing.
     *
     * @return list<array{?string, string}>
     */
    private static function emailPieces(string $text): array
    {
        $parts = preg_split(self::EMAIL, $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        $pieces = [];

        foreach (false === $parts ? [$text] : $parts as $part) {
            $pieces[] = [1 === preg_match(self::EMAIL, $part) ? 'mailto:'.$part : null, $part];
        }

        return $pieces;
    }
}
