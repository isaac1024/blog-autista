<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\RichTextComponents;

final class RichTextComponentsTest extends TestCase
{
    private const string PUBLISHED = '01a1b801-ce58-723d-8234-b76d9105d001';
    private const string MISSING = '01a1b801-ce58-723d-8234-b76d9105d002';
    private const string OTHER_PUBLISHED = '01a1b801-ce58-723d-8234-b76d9105d003';

    public function testItLinksOnlyRunsWhosePageIsOnTheSite(): void
    {
        $content = RichText::fromArray([
            'version' => 1,
            'blocks' => [[
                'type' => 'paragraph',
                'text' => [
                    ['text' => 'Texto '],
                    ['text' => 'publicado', 'marks' => ['bold', 'link'], 'page_id' => self::PUBLISHED],
                    ['text' => ' y no publicado', 'marks' => ['link'], 'page_id' => self::MISSING],
                ],
            ]],
        ]);

        $components = new RichTextComponents()($content, [], [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí']]);

        self::assertSame([self::PUBLISHED, self::MISSING], $content->pageIds());
        self::assertSame('Shared:ContentParagraph', $components[0]->name);
        self::assertSame([
            ['href' => null, 'runs' => [['text' => 'Texto ', 'bold' => false]]],
            ['href' => '/sobre-mi.html', 'runs' => [['text' => 'publicado', 'bold' => true]]],
            ['href' => null, 'runs' => [['text' => ' y no publicado', 'bold' => false]]],
        ], $components[0]->props['text']);
    }

    /**
     * A link partly bold comes as one stretch with the address of the page,
     * whose runs keep each its own format, so it is painted as one link. The
     * same in every block that holds text.
     *
     * @param array<string, mixed> $block
     * @param list<int|string>     $path  where the component keeps the runs among its props
     */
    #[DataProvider('blocksWithALinkInSeveralFormats')]
    public function testItLinksEveryFormatOfALinkToTheSamePage(string $component, array $block, array $path): void
    {
        $components = new RichTextComponents()(
            RichText::fromArray(['version' => 1, 'blocks' => [$block]]),
            [],
            [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí']],
        );

        $runs = $components[0]->props;

        foreach ($path as $key) {
            self::assertIsArray($runs);
            $runs = $runs[$key];
        }

        self::assertSame($component, $components[0]->name);
        self::assertSame([
            ['href' => null, 'runs' => [['text' => 'Lee ', 'bold' => false]]],
            ['href' => '/sobre-mi.html', 'runs' => [
                ['text' => 'quién ', 'bold' => false],
                ['text' => 'escribe', 'bold' => true],
                ['text' => ' el blog', 'bold' => false],
            ]],
            ['href' => null, 'runs' => [['text' => '.', 'bold' => false]]],
        ], $runs);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, list<int|string>}>
     */
    public static function blocksWithALinkInSeveralFormats(): iterable
    {
        $text = [
            ['text' => 'Lee '],
            ['text' => 'quién ', 'marks' => ['link'], 'page_id' => self::PUBLISHED],
            ['text' => 'escribe', 'marks' => ['bold', 'link'], 'page_id' => self::PUBLISHED],
            ['text' => ' el blog', 'marks' => ['link'], 'page_id' => self::PUBLISHED],
            ['text' => '.'],
        ];

        yield 'paragraph' => [
            'Shared:ContentParagraph',
            ['type' => 'paragraph', 'text' => $text],
            ['text'],
        ];
        yield 'heading' => [
            'Shared:ContentHeading',
            ['type' => 'heading', 'level' => 2, 'text' => $text],
            ['text'],
        ];
        yield 'quote' => [
            'Shared:ContentQuote',
            ['type' => 'quote', 'paragraphs' => [$text]],
            ['paragraphs', 0],
        ];
        yield 'list item' => [
            'Shared:ContentList',
            ['type' => 'list', 'style' => 'unordered', 'items' => [$text]],
            ['items', 0],
        ];
        yield 'callout' => [
            'Shared:ContentCallout',
            ['type' => 'callout', 'variant' => 'note', 'paragraphs' => [$text]],
            ['paragraphs', 0],
        ];
        yield 'table header' => [
            'Shared:ContentTable',
            ['type' => 'table', 'header' => [$text], 'rows' => [[[['text' => 'Celda']]]]],
            ['header', 0],
        ];
        yield 'table cell' => [
            'Shared:ContentTable',
            ['type' => 'table', 'header' => [[['text' => 'Columna']]], 'rows' => [[$text]]],
            ['rows', 0, 0],
        ];
    }

    /**
     * @param list<array<string, mixed>>                                              $text
     * @param list<array{href: ?string, runs: list<array{text: string, bold: bool}>}> $expected
     */
    #[DataProvider('stretches')]
    public function testItGroupsTheRunsInARowThatLinkTheSamePage(array $text, array $expected): void
    {
        $components = new RichTextComponents()(
            RichText::fromArray(['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => $text]]]),
            [],
            [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí'], self::OTHER_PUBLISHED => ['url' => '/contacto.html', 'title' => 'Contacto']],
        );

        self::assertSame($expected, $components[0]->props['text']);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>}>
     */
    public static function stretches(): iterable
    {
        yield 'plain text in several formats, one stretch linking none' => [
            [['text' => 'Solo '], ['text' => 'técnicas', 'marks' => ['bold']], ['text' => '.']],
            [['href' => null, 'runs' => [['text' => 'Solo ', 'bold' => false], ['text' => 'técnicas', 'bold' => true], ['text' => '.', 'bold' => false]]]],
        ];
        yield 'links to two pages in a row stay apart' => [
            [
                ['text' => 'Sobre mí', 'marks' => ['link'], 'page_id' => self::PUBLISHED],
                ['text' => 'Contacto', 'marks' => ['bold', 'link'], 'page_id' => self::OTHER_PUBLISHED],
            ],
            [
                ['href' => '/sobre-mi.html', 'runs' => [['text' => 'Sobre mí', 'bold' => false]]],
                ['href' => '/contacto.html', 'runs' => [['text' => 'Contacto', 'bold' => true]]],
            ],
        ];
        yield 'two links to one page apart are two links' => [
            [
                ['text' => 'aquí', 'marks' => ['link'], 'page_id' => self::PUBLISHED],
                ['text' => ' o '],
                ['text' => 'allí', 'marks' => ['link'], 'page_id' => self::PUBLISHED],
            ],
            [
                ['href' => '/sobre-mi.html', 'runs' => [['text' => 'aquí', 'bold' => false]]],
                ['href' => null, 'runs' => [['text' => ' o ', 'bold' => false]]],
                ['href' => '/sobre-mi.html', 'runs' => [['text' => 'allí', 'bold' => false]]],
            ],
        ];
        yield 'a link off the site joins the plain text around it' => [
            [
                ['text' => 'Lee '],
                ['text' => 'el borrador', 'marks' => ['bold', 'link'], 'page_id' => self::MISSING],
                ['text' => ' luego.'],
            ],
            [['href' => null, 'runs' => [['text' => 'Lee ', 'bold' => false], ['text' => 'el borrador', 'bold' => true], ['text' => ' luego.', 'bold' => false]]]],
        ];
    }

    /**
     * Every kind of block is painted by its own component, with what it
     * says, as the component takes it.
     */
    public function testItPaintsEveryKindOfBlockWithWhatItSays(): void
    {
        $components = new RichTextComponents()(RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => [['text' => 'Título']]],
            ['type' => 'quote', 'paragraphs' => [[['text' => 'Cita']]], 'source' => 'Alguien'],
            ['type' => 'list', 'style' => 'ordered', 'items' => [[['text' => 'Uno']]]],
            ['type' => 'list', 'style' => 'unordered', 'items' => [[['text' => 'Dos']]]],
            ['type' => 'callout', 'variant' => 'important', 'paragraphs' => [[['text' => 'Ojo']]]],
        ]]), [], []);
        ComponentContracts::assertHonoured(Page::create('Contenido')->withMain('Shared:Prose', [], $components));

        self::assertSame(
            [
                ['Shared:ContentHeading', ['level' => 2]],
                ['Shared:ContentQuote', ['source' => 'Alguien']],
                ['Shared:ContentList', ['ordered' => true]],
                ['Shared:ContentList', ['ordered' => false]],
                ['Shared:ContentCallout', ['variant' => 'important']],
            ],
            array_map(static fn (PageComponent $component): array => [
                $component->name,
                array_diff_key($component->props, array_flip(['text', 'paragraphs', 'items'])),
            ], $components),
        );
    }

    /**
     * A widget whose page isn't on the site, or an image off it, leaves out
     * only itself: the rest of the content is still painted.
     */
    public function testItPaintsTheRestOfTheContentAfterWhatItLeavesOut(): void
    {
        $components = new RichTextComponents()(RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::MISSING],
            ['type' => 'paragraph', 'text' => [['text' => 'Después']]],
            ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::PUBLISHED],
            ['type' => 'paragraph', 'text' => [['text' => 'Al final']]],
        ]]), [], [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí']]);

        self::assertSame(
            ['Shared:ContentParagraph', 'Shared:ContentRelatedPage', 'Shared:ContentParagraph'],
            array_map(static fn (PageComponent $component): string => $component->name, $components),
        );
    }

    /**
     * A post, a section or a tag, or an author is linked and shown in a
     * widget as a page is, by the id it is given under, whatever its kind:
     * ids are never shared.
     */
    public function testItLinksAndShowsEveryKindOfContentAsAPage(): void
    {
        $content = RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'paragraph', 'text' => [
                ['text' => 'un post', 'marks' => ['link'], 'post_id' => self::PUBLISHED],
                ['text' => ' y un autor', 'marks' => ['link'], 'author_id' => self::OTHER_PUBLISHED],
                ['text' => ' y una sección que no está', 'marks' => ['link'], 'term_id' => self::MISSING],
            ]],
            ['type' => 'widget', 'kind' => 'related_page', 'term_id' => self::OTHER_PUBLISHED],
        ]]);

        $components = new RichTextComponents()($content, [], [
            self::PUBLISHED => ['url' => '/vida-diaria/2026/11/07/un-post.html', 'title' => 'Un post'],
            self::OTHER_PUBLISHED => ['url' => '/autor/isaac.html', 'title' => 'Isaac'],
        ]);

        self::assertSame([
            ['href' => '/vida-diaria/2026/11/07/un-post.html', 'runs' => [['text' => 'un post', 'bold' => false]]],
            ['href' => '/autor/isaac.html', 'runs' => [['text' => ' y un autor', 'bold' => false]]],
            ['href' => null, 'runs' => [['text' => ' y una sección que no está', 'bold' => false]]],
        ], $components[0]->props['text']);
        self::assertSame(['Shared:ContentRelatedPage', ['title' => 'Isaac', 'url' => '/autor/isaac.html']], [$components[1]->name, $components[1]->props]);
        self::assertSame([], $content->pageIds());
    }
}
