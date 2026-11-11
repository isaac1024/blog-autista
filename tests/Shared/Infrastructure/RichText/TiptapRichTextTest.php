<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\RichText;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\InvalidRichText;
use Shared\Infrastructure\RichText\TiptapRichText;

/**
 * The adapter between the editor's documents and the stored format: what the
 * editor produces comes through whole, its leftovers are dropped, and nothing
 * it doesn't know slips by.
 */
final class TiptapRichTextTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $blocks
     */
    #[DataProvider('documents')]
    public function testItTranslatesTheEditorDocumentIntoTheStoredFormat(array $nodes, array $blocks): void
    {
        self::assertSame(
            ['version' => 1, 'blocks' => $blocks],
            new TiptapRichText()->toStored(self::document($nodes)),
        );
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, list<array<string, mixed>>}>
     */
    public static function documents(): iterable
    {
        yield 'blank document' => [[['type' => 'paragraph']], []];
        yield 'heading' => [
            [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::text('Detalle')]]],
            [['type' => 'heading', 'level' => 2, 'text' => [['text' => 'Detalle']]]],
        ];
        yield 'paragraph with bold' => [
            [['type' => 'paragraph', 'content' => [self::text('Solo '), self::text('técnicas', 'bold')]]],
            [['type' => 'paragraph', 'text' => [['text' => 'Solo '], ['text' => 'técnicas', 'marks' => ['bold']]]]],
        ];
        yield 'paragraph with a bold link to a page' => [
            [['type' => 'paragraph', 'content' => [
                self::text('Lee '),
                ['type' => 'text', 'text' => 'la página', 'marks' => [
                    ['type' => 'bold'],
                    ['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']],
                ]],
            ]]],
            [['type' => 'paragraph', 'text' => [
                ['text' => 'Lee '],
                ['text' => 'la página', 'marks' => ['bold', 'link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001'],
            ]]],
        ];
        yield 'link to a page, partly bold' => [
            [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'quién ', 'marks' => [['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']]]],
                ['type' => 'text', 'text' => 'escribe', 'marks' => [
                    ['type' => 'bold'],
                    ['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']],
                ]],
                ['type' => 'text', 'text' => ' el blog', 'marks' => [['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']]]],
            ]]],
            [['type' => 'paragraph', 'text' => [
                ['text' => 'quién ', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001'],
                ['text' => 'escribe', 'marks' => ['bold', 'link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001'],
                ['text' => ' el blog', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001'],
            ]]],
        ];
        yield 'quote' => [
            [['type' => 'blockquote', 'content' => [self::paragraph('Uno'), self::paragraph('Dos')]]],
            [['type' => 'quote', 'paragraphs' => [[['text' => 'Uno']], [['text' => 'Dos']]]]],
        ];
        yield 'quote with whom it is from, as plain text' => [
            [['type' => 'blockquote', 'content' => [self::paragraph('Uno'), ['type' => 'quoteSource', 'content' => [self::text(' Ana'), self::text(' Pérez ')]]]]],
            [['type' => 'quote', 'paragraphs' => [[['text' => 'Uno']]], 'source' => 'Ana Pérez']],
        ];
        yield 'quote whose source is left blank' => [
            [['type' => 'blockquote', 'content' => [self::paragraph('Uno'), ['type' => 'quoteSource']]]],
            [['type' => 'quote', 'paragraphs' => [[['text' => 'Uno']]]]],
        ];
        yield 'widget, by the page it shows' => [
            [['type' => 'widget', 'attrs' => ['kind' => 'related_page', 'id' => '01a1b801-ce58-723d-8234-b76d9105d001']]],
            [['type' => 'widget', 'kind' => 'related_page', 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001']],
        ];
        yield 'callout' => [
            [['type' => 'callout', 'attrs' => ['variant' => 'note'], 'content' => [self::paragraph('Ojo')]]],
            [['type' => 'callout', 'variant' => 'note', 'paragraphs' => [[['text' => 'Ojo']]]]],
        ];
        yield 'bulleted list' => [
            [['type' => 'bulletList', 'content' => [self::item('Uno'), self::item('Dos')]]],
            [['type' => 'list', 'style' => 'unordered', 'items' => [[['text' => 'Uno']], [['text' => 'Dos']]]]],
        ];
        yield 'numbered list, whose attributes are the editor\'s own' => [
            [['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [self::item('Primero')]]],
            [['type' => 'list', 'style' => 'ordered', 'items' => [[['text' => 'Primero']]]]],
        ];
        yield 'table, whose first row is the header' => [
            [self::table([['tableHeader', 'Nombre'], ['tableHeader', 'Finalidad']], [['tableCell', 'PHPSESSID'], ['tableCell', '']])],
            [['type' => 'table', 'header' => [[['text' => 'Nombre']], [['text' => 'Finalidad']]], 'rows' => [[[['text' => 'PHPSESSID']], []]]]],
        ];
        yield 'image, embedded by its id alone' => [
            [['type' => 'image', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']]],
            [['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d001']],
        ];
        yield 'empty blocks the editor leaves behind are dropped' => [
            [
                ['type' => 'paragraph'],
                ['type' => 'heading', 'attrs' => ['level' => 2]],
                ['type' => 'blockquote', 'content' => [['type' => 'paragraph']]],
                ['type' => 'callout', 'attrs' => ['variant' => 'note'], 'content' => [['type' => 'paragraph']]],
                ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]],
                ['type' => 'table', 'content' => []],
                self::paragraph('Lo único que queda'),
            ],
            [['type' => 'paragraph', 'text' => [['text' => 'Lo único que queda']]]],
        ];
        yield 'empty paragraphs and items are dropped from their blocks' => [
            [
                ['type' => 'blockquote', 'content' => [['type' => 'paragraph'], self::paragraph('Cita')]],
                ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]], self::item('Uno')]],
            ],
            [
                ['type' => 'quote', 'paragraphs' => [[['text' => 'Cita']]]],
                ['type' => 'list', 'style' => 'unordered', 'items' => [[['text' => 'Uno']]]],
            ],
        ];
        yield 'body rows of a table left blank are dropped' => [
            [self::table([['tableHeader', 'Nombre']], [['tableCell', 'Sesión']], [['tableCell', '']], [['tableCell', 'Idioma']])],
            [['type' => 'table', 'header' => [[['text' => 'Nombre']]], 'rows' => [[[['text' => 'Sesión']]], [[['text' => 'Idioma']]]]]],
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function testItRejectsADocumentTheEditorDoesNotProduce(string $json, InvalidRichText $error): void
    {
        $this->expectExceptionObject($error);

        new TiptapRichText()->toStored($json);
    }

    /**
     * @return iterable<string, array{string, InvalidRichText}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'not JSON' => ['<p>Hola</p>', InvalidRichText::malformed('document', 'valid JSON')];
        yield 'not a node' => ['"hola"', InvalidRichText::malformed('document', 'a node with a type')];
        yield 'not a document' => ['{"type":"paragraph"}', InvalidRichText::malformed('document.type', '"doc"')];
        yield 'content that is not a list' => ['{"type":"doc","content":{"type":"paragraph"}}', InvalidRichText::malformed('document.content', 'a list')];
        yield 'unknown block, never dropped' => [
            self::document([['type' => 'video', 'attrs' => ['src' => 'x.mp4']]]),
            InvalidRichText::malformed('document.content[0].type', 'a block the editor offers'),
        ];
        yield 'node without a type' => [self::document([['content' => []]]), InvalidRichText::malformed('document.content[0]', 'a node with a type')];
        yield 'inline node other than text' => [
            self::document([['type' => 'paragraph', 'content' => [['type' => 'hardBreak']]]]),
            InvalidRichText::malformed('document.content[0].content[0].type', '"text"'),
        ];
        yield 'nested list' => [
            self::document([['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [self::paragraph('Uno'), ['type' => 'bulletList', 'content' => []]]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].content', 'a single paragraph'),
        ];
        yield 'header row with a body cell' => [
            self::document([self::table([['tableCell', 'Nombre']], [['tableCell', 'Sesión']])]),
            InvalidRichText::malformed('document.content[0].content[0].content[0].type', '"tableHeader"'),
        ];
        yield 'merged cell' => [
            self::document([['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'attrs' => ['colspan' => 2, 'rowspan' => 1], 'content' => [self::paragraph('Nombre')]],
            ]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].content[0]', 'a cell that spans a single row and column'),
        ];
        yield 'cell spanning two rows' => [
            self::document([['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'attrs' => ['colspan' => 1, 'rowspan' => 2, 'colwidth' => null], 'content' => [self::paragraph('Nombre')]],
            ]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].content[0]', 'a cell that spans a single row and column'),
        ];
        yield 'cell holding something other than a paragraph' => [
            self::document([['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'content' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::text('Nombre')]]]],
            ]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].content[0].content[0].type', '"paragraph"'),
        ];
        yield 'marks that are not a list' => [
            self::document([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola', 'marks' => 'bold']]]]),
            InvalidRichText::malformed('document.content[0].content[0].marks', 'a list'),
        ];
        yield 'cell with two paragraphs' => [
            self::document([['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'content' => [self::paragraph('Uno'), self::paragraph('Dos')]],
            ]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].content[0].content', 'a single paragraph'),
        ];
        yield 'source of a quote with marks' => [
            self::document([['type' => 'blockquote', 'content' => [self::paragraph('Uno'), ['type' => 'quoteSource', 'content' => [self::text('Ana', 'bold')]]]]]),
            InvalidRichText::malformed('document.content[0].content[1].content[0].marks', 'empty: this text takes no marks'),
        ];
        yield 'source of a quote holding something other than text' => [
            self::document([['type' => 'blockquote', 'content' => [self::paragraph('Uno'), ['type' => 'quoteSource', 'content' => [self::paragraph('Ana')]]]]]),
            InvalidRichText::malformed('document.content[0].content[1].content[0].type', '"text"'),
        ];
        yield 'source of a quote whose text is not a string' => [
            self::document([['type' => 'blockquote', 'content' => [self::paragraph('Uno'), ['type' => 'quoteSource', 'content' => [['type' => 'text', 'text' => 5]]]]]]),
            InvalidRichText::malformed('document.content[0].content[1].content[0].text', 'a string'),
        ];
        yield 'attributes that are not an object' => [
            self::document([['type' => 'callout', 'attrs' => 'note', 'content' => [self::paragraph('Ojo')]]]),
            InvalidRichText::malformed('document.content[0].attrs', 'an object'),
        ];
        yield 'text linking two pages' => [
            self::document([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Enlace', 'marks' => [
                ['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']],
                ['type' => 'link', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d002']],
            ]]]]]),
            InvalidRichText::malformed('document.content[0].content[0].marks[1]', 'a single link'),
        ];
    }

    public function testItLeavesUnknownMarksToTheDomain(): void
    {
        // Marks pass through as they come: the stored format decides which exist.
        self::assertSame(
            ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'cursiva', 'marks' => ['italic']]]]]],
            new TiptapRichText()->toStored(self::document([['type' => 'paragraph', 'content' => [self::text('cursiva', 'italic')]]])),
        );
    }

    /**
     * @param array<string, mixed> $run
     */
    #[DataProvider('invalidStoredLinks')]
    public function testItRejectsAnInvalidStoredLink(array $run, InvalidRichText $error): void
    {
        $this->expectExceptionObject($error);

        new TiptapRichText()->toTiptap(['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [$run]]]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, InvalidRichText}>
     */
    public static function invalidStoredLinks(): iterable
    {
        $path = 'blocks[0].text[0].page_id';
        $inconsistent = InvalidRichText::malformed($path, 'a page id exactly when the text has the link mark');

        yield 'link without a page' => [['text' => 'Enlace', 'marks' => ['link']], $inconsistent];
        yield 'page without a link' => [['text' => 'Texto', 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d001'], $inconsistent];
        yield 'page id malformed' => [
            ['text' => 'Enlace', 'marks' => ['link'], 'page_id' => 'pagina'],
            InvalidRichText::malformed($path, 'the id of a page'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    #[DataProvider('storedDocuments')]
    public function testItGivesTheEditorBackWhatItStored(array $blocks): void
    {
        $tiptap = new TiptapRichText();
        $stored = ['version' => 1, 'blocks' => $blocks];

        self::assertSame($stored, $tiptap->toStored(json_encode($tiptap->toTiptap($stored), \JSON_THROW_ON_ERROR)));
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function storedDocuments(): iterable
    {
        foreach (self::documents() as $name => [, $blocks]) {
            if ([] !== $blocks) {
                yield $name => [$blocks];
            }
        }
    }

    public function testItOpensAnEmptyDocumentWithAnEmptyParagraph(): void
    {
        // Tiptap documents always hold a block.
        self::assertSame(
            ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            new TiptapRichText()->toTiptap(['version' => 1, 'blocks' => []]),
        );
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private static function document(array $nodes): string
    {
        return json_encode(['type' => 'doc', 'content' => $nodes], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $text, string ...$marks): array
    {
        return [] === $marks
            ? ['type' => 'text', 'text' => $text]
            : ['type' => 'text', 'text' => $text, 'marks' => array_map(static fn (string $mark): array => ['type' => $mark], $marks)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [self::text($text)]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(string $text): array
    {
        return ['type' => 'listItem', 'content' => [self::paragraph($text)]];
    }

    /**
     * @param list<array{string, string}> ...$rows cells as [node type, text]; an empty text leaves the cell empty
     *
     * @return array<string, mixed>
     */
    private static function table(array ...$rows): array
    {
        return ['type' => 'table', 'content' => array_map(
            static fn (array $row): array => ['type' => 'tableRow', 'content' => array_map(
                static fn (array $cell): array => [
                    'type' => $cell[0],
                    'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                    'content' => ['' === $cell[1] ? ['type' => 'paragraph'] : self::paragraph($cell[1])],
                ],
                $row,
            )],
            $rows,
        )];
    }
}
