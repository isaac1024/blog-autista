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

    private const array PICTURE = ['src' => '/media/a-800.webp', 'srcset' => '/media/a-800.webp 800w', 'width' => 800, 'height' => 450];

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

        $components = new RichTextComponents()($content, [], [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí']], []);

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
            [],
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
            [],
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
        yield 'an email address written links itself, the full stop after it apart' => [
            [['text' => 'Escríbeme a isaac.abreu@blogautista.com.']],
            [
                ['href' => null, 'runs' => [['text' => 'Escríbeme a ', 'bold' => false]]],
                ['href' => 'mailto:isaac.abreu@blogautista.com', 'runs' => [['text' => 'isaac.abreu@blogautista.com', 'bold' => false]]],
                ['href' => null, 'runs' => [['text' => '.', 'bold' => false]]],
            ],
        ];
        yield 'an email address in bold links itself, still bold' => [
            [['text' => 'Correo: '], ['text' => 'ana@ejemplo.es', 'marks' => ['bold']], ['text' => ' o '], ['text' => 'luis+blog@correo.ejemplo.org', 'marks' => ['bold']]],
            [
                ['href' => null, 'runs' => [['text' => 'Correo: ', 'bold' => false]]],
                ['href' => 'mailto:ana@ejemplo.es', 'runs' => [['text' => 'ana@ejemplo.es', 'bold' => true]]],
                ['href' => null, 'runs' => [['text' => ' o ', 'bold' => false]]],
                ['href' => 'mailto:luis+blog@correo.ejemplo.org', 'runs' => [['text' => 'luis+blog@correo.ejemplo.org', 'bold' => true]]],
            ],
        ];
        yield 'text that only looks like an address links nothing' => [
            [['text' => 'Usa @usuario, a@b o nombre@dominio sin punto.']],
            [['href' => null, 'runs' => [['text' => 'Usa @usuario, a@b o nombre@dominio sin punto.', 'bold' => false]]]],
        ];
        yield 'an email address in a link keeps the link' => [
            [['text' => 'ana@ejemplo.es', 'marks' => ['link'], 'page_id' => self::PUBLISHED]],
            [['href' => '/sobre-mi.html', 'runs' => [['text' => 'ana@ejemplo.es', 'bold' => false]]]],
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
        ]]), [], [], []);
        ComponentContracts::assertHonoured(Page::create('Contenido')->withMain('Shared:Prose', [], $components));

        self::assertSame(
            [
                ['Shared:ContentHeading', ['level' => 2, 'anchor' => 'titulo']],
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
    /**
     * Each heading is anchored by the words of its text, as an address reads
     * them: two of one text never share one, nor does one take an anchor the
     * page gives something else, such as its contents; one with no word is a
     * part.
     */
    public function testItAnchorsEveryHeadingByItsWords(): void
    {
        $heading = static fn (int $level, string $text): array => ['type' => 'heading', 'level' => $level, 'text' => [['text' => $text, 'marks' => ['bold']]]];
        $components = new RichTextComponents()(RichText::fromArray(['version' => 1, 'blocks' => [
            $heading(2, ' Qué es la comunicación aumentativa '),
            ['type' => 'paragraph', 'text' => [['text' => 'Texto']]],
            $heading(3, 'Qué es la comunicación aumentativa'),
            $heading(2, 'Índice'),
            $heading(2, '¿…?'),
            $heading(2, str_repeat('palabra ', 12)),
            // Cut at the same length whatever signs come before the words.
            $heading(2, '¿'.str_repeat('palabra ', 12)),
        ]]), [], [], []);

        self::assertSame(
            ['que-es-la-comunicacion-aumentativa', 'que-es-la-comunicacion-aumentativa-2', 'indice-2', 'apartado', substr(str_repeat('palabra-', 10), 0, 79), substr(str_repeat('palabra-', 10), 0, 79).'-2'],
            array_values(array_map(static fn (PageComponent $component): mixed => $component->props['anchor'], array_filter($components, static fn (PageComponent $component): bool => 'Shared:ContentHeading' === $component->name))),
        );
    }

    public function testItPaintsTheRestOfTheContentAfterWhatItLeavesOut(): void
    {
        $components = new RichTextComponents()(RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::MISSING],
            ['type' => 'paragraph', 'text' => [['text' => 'Después']]],
            ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::PUBLISHED],
            ['type' => 'paragraph', 'text' => [['text' => 'Al final']]],
        ]]), [], [self::PUBLISHED => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí']], []);

        self::assertSame(
            ['Shared:ContentParagraph', 'Shared:ContentRelated', 'Shared:ContentParagraph'],
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
            self::OTHER_PUBLISHED => ['url' => '/autor/isaac.html', 'title' => 'Isaac', 'related' => [
                'kind' => 'author', 'summary' => 'Quién escribe.', 'image' => self::PICTURE, 'section' => null, 'published' => null,
            ]],
        ], []);

        self::assertSame([
            ['href' => '/vida-diaria/2026/11/07/un-post.html', 'runs' => [['text' => 'un post', 'bold' => false]]],
            ['href' => '/autor/isaac.html', 'runs' => [['text' => ' y un autor', 'bold' => false]]],
            ['href' => null, 'runs' => [['text' => ' y una sección que no está', 'bold' => false]]],
        ], $components[0]->props['text']);
        self::assertSame(['Shared:ContentRelated', [
            'kind' => 'author',
            'url' => '/autor/isaac.html',
            'title' => 'Isaac',
            'summary' => 'Quién escribe.',
            'image' => self::PICTURE,
            'section' => null,
            'published' => null,
            'lead' => true,
        ]], [$components[1]->name, $components[1]->props]);
        self::assertSame([], $content->pageIds());
    }

    /**
     * The first picture of the text may be the largest thing on the page as
     * it opens, so it is fetched at once, whatever shows it; what shows none
     * leaves it to the next. The opening image of a content is fetched
     * first anyway.
     */
    public function testItFetchesTheFirstPictureOfTheTextAtOnce(): void
    {
        self::assertSame([null, false, true, false, false], self::leads([
            ['type' => 'paragraph', 'text' => [['text' => 'Antes']]],
            ['type' => 'widget', 'kind' => 'related_page', 'external_id' => self::MISSING],
            ['type' => 'image', 'id' => self::PUBLISHED],
            ['type' => 'widget', 'kind' => 'related_page', 'external_id' => self::OTHER_PUBLISHED],
            ['type' => 'image', 'id' => self::PUBLISHED],
        ]));
        self::assertSame([true, false], self::leads([
            ['type' => 'widget', 'kind' => 'related_page', 'external_id' => self::OTHER_PUBLISHED],
            ['type' => 'image', 'id' => self::PUBLISHED],
        ]));
        self::assertSame([false, true, false], self::leads([
            ['type' => 'mosaic', 'limit' => 3, 'section_id' => null, 'author_id' => null, 'tag_ids' => []],
            ['type' => 'mosaic', 'limit' => 4, 'section_id' => null, 'author_id' => null, 'tag_ids' => []],
            ['type' => 'image', 'id' => self::PUBLISHED],
        ]), 'a mosaic whose first card shows no picture leaves it to the next');
        self::assertFalse(new RichTextComponents()->opening(self::image(), [])->props['lead']);
    }

    /**
     * Whether each component the blocks are painted with is fetched at once,
     * null for those that show no picture.
     *
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<mixed>
     */
    private static function leads(array $blocks): array
    {
        $components = new RichTextComponents()(RichText::fromArray(['version' => 1, 'blocks' => $blocks]), [self::PUBLISHED => self::image()], [
            self::MISSING => self::external('sin-imagen', null),
            self::OTHER_PUBLISHED => self::external('con-imagen', self::PICTURE),
        ], ['3|||' => [self::card(null), self::card(self::PICTURE)], '4|||' => [self::card(self::PICTURE)]]);

        return array_map(static fn (PageComponent $component): mixed => $component->props['lead'] ?? null, $components);
    }

    /**
     * @return array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}
     */
    private static function image(): array
    {
        return [...self::PICTURE, 'alt' => 'Una imagen', 'caption' => RichText::empty()];
    }

    /**
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     *
     * @return array{url: string, title: string, external: array{kind: string, provider: ?string, locator: ?string, url: string, title: string, summary: ?string, language: string, details: array<array-key, mixed>, image: ?array{src: string, srcset: string, width: int, height: int}, cookies: ?string}}
     */
    private static function external(string $title, ?array $image): array
    {
        $url = 'https://www.example.org/'.$title;

        return ['url' => $url, 'title' => $title, 'external' => [
            'kind' => 'article', 'provider' => null, 'locator' => null, 'url' => $url, 'title' => $title,
            'summary' => null, 'language' => 'es', 'details' => [], 'image' => $image, 'cookies' => null,
        ]];
    }

    /**
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     *
     * @return array{url: string, title: string, excerpt: ?string, section: string, date: string, datetime: string, image: ?array{src: string, srcset: string, width: int, height: int}}
     */
    private static function card(?array $image): array
    {
        return ['url' => '/un-post.html', 'title' => 'Un post', 'excerpt' => null, 'section' => 'Vida diaria', 'date' => '17 de noviembre de 2026', 'datetime' => '2026-11-17', 'image' => $image];
    }
}
