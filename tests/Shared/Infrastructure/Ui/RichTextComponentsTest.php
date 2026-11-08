<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
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

        $components = new RichTextComponents()($content, [], [self::PUBLISHED => '/sobre-mi.html']);

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
            [self::PUBLISHED => '/sobre-mi.html'],
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
            [self::PUBLISHED => '/sobre-mi.html', self::OTHER_PUBLISHED => '/contacto.html'],
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
}
