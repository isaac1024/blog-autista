<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\RichTextEditor;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * What the editor of rich text offers, from the features of its module and
 * from where it finds images and pages: the kinds of block it turns one
 * into, and whether it embeds images, links text and shows widgets. The
 * labels are translation keys the catalogues must define.
 */
final class RichTextEditorTest extends TestCase
{
    private const array EVERYTHING = [
        'blocks' => ['paragraph', 'heading', 'quote', 'list', 'callout', 'table', 'image', 'widget'],
        'headingLevels' => [2, 3],
        'calloutVariants' => ['note', 'important'],
        'marks' => ['bold', 'link'],
    ];

    private const array NOTHING = ['blocks' => ['paragraph'], 'headingLevels' => [], 'calloutVariants' => [], 'marks' => []];

    /**
     * A paragraph can always be chosen; every other kind, only as the module
     * allows it, a heading at each of its levels and a callout in each of
     * its variants.
     */
    public function testItOffersTheBlocksTheModuleAllows(): void
    {
        self::assertSame([
            ['paragraph', 'ui.editor.paragraph', []],
            ['heading:2', 'ui.editor.heading', ['%level%' => 2]],
            ['heading:3', 'ui.editor.heading', ['%level%' => 3]],
            ['quote', 'ui.editor.quote', []],
            ['bulletList', 'ui.editor.bullet_list', []],
            ['orderedList', 'ui.editor.ordered_list', []],
            ['callout:note', 'ui.editor.callout.note', []],
            ['callout:important', 'ui.editor.callout.important', []],
        ], self::options(self::editor(self::EVERYTHING)));
        self::assertSame([['paragraph', 'ui.editor.paragraph', []]], self::options(self::editor(self::NOTHING)));
        self::assertSame(['note' => 'ui.editor.callout.note', 'important' => 'ui.editor.callout.important'], self::editor(self::EVERYTHING)->calloutLabels());
        self::assertSame([], self::editor(self::NOTHING)->calloutLabels());
    }

    /**
     * Headings and callouts are offered only when the module allows them,
     * whatever levels or variants it lists.
     */
    public function testItOffersNoHeadingNorCalloutTheModuleDoesntAllow(): void
    {
        $editor = self::editor(['blocks' => ['paragraph'], 'headingLevels' => [2], 'calloutVariants' => ['note'], 'marks' => []]);

        self::assertSame(['paragraph'], array_column(self::options($editor), 0));
    }

    /**
     * Bold, tables and quotes are offered as the module allows them.
     */
    public function testItOffersTheFormatsTheModuleAllows(): void
    {
        $everything = self::editor(self::EVERYTHING);
        $nothing = self::editor(self::NOTHING);

        self::assertSame([true, true, true], [$everything->allowsBold(), $everything->allowsTables(), $everything->allowsQuotes()]);
        self::assertSame([false, false, false], [$nothing->allowsBold(), $nothing->allowsTables(), $nothing->allowsQuotes()]);
    }

    /**
     * Images, links and widgets need the module to allow them and the editor
     * to know where to find what they show: either missing, there are none.
     *
     * @param array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>} $features
     * @param array{images: bool, links: bool, widgets: bool, pages: bool}                                              $offered
     */
    #[DataProvider('pickers')]
    public function testItEmbedsAndLinksOnlyWhatItCanFind(array $features, ?string $imageSearch, ?string $imageLookup, ?string $pageSearch, ?string $pageLookup, array $offered): void
    {
        $editor = new RichTextEditor();
        $editor->mount('content', 'content', 'Contenido', '{}', $features, imageSearchUrl: $imageSearch, imageLookupUrl: $imageLookup, pageSearchUrl: $pageSearch, pageLookupUrl: $pageLookup);

        self::assertSame($offered, [
            'images' => $editor->allowsImages(),
            'links' => $editor->allowsLinks(),
            'widgets' => $editor->allowsWidgets(),
            'pages' => $editor->picksPages(),
        ]);
    }

    /**
     * @return iterable<string, array{array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>}, ?string, ?string, ?string, ?string, array{images: bool, links: bool, widgets: bool, pages: bool}}>
     */
    public static function pickers(): iterable
    {
        $none = ['images' => false, 'links' => false, 'widgets' => false, 'pages' => false];

        yield 'allowed, and found' => [self::EVERYTHING, '/i', '/i', '/p', '/p', ['images' => true, 'links' => true, 'widgets' => true, 'pages' => true]];
        yield 'allowed, and found nowhere' => [self::EVERYTHING, null, null, null, null, $none];
        yield 'allowed, without a search' => [self::EVERYTHING, null, '/i', null, '/p', $none];
        yield 'allowed, without a lookup' => [self::EVERYTHING, '/i', null, '/p', null, $none];
        yield 'not allowed, and found' => [self::NOTHING, '/i', '/i', '/p', '/p', $none];
        yield 'links only' => [['blocks' => ['paragraph'], 'headingLevels' => [], 'calloutVariants' => [], 'marks' => ['link']], null, null, '/p', '/p', ['images' => false, 'links' => true, 'widgets' => false, 'pages' => true]];
        yield 'widgets only' => [['blocks' => ['paragraph', 'widget'], 'headingLevels' => [], 'calloutVariants' => [], 'marks' => []], null, null, '/p', '/p', ['images' => false, 'links' => false, 'widgets' => true, 'pages' => true]];
    }

    /**
     * Its label shows, and it holds several paragraphs, unless told.
     */
    public function testItShowsItsLabelAndHoldsSeveralParagraphsUnlessTold(): void
    {
        $editor = self::editor(self::NOTHING);

        self::assertSame([false, false], [$editor->hideLabel, $editor->singleParagraph]);
    }

    /**
     * @param array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>} $features
     */
    private static function editor(array $features): RichTextEditor
    {
        $editor = new RichTextEditor();
        $editor->mount('content', 'content', 'Contenido', '{}', $features);

        return $editor;
    }

    /**
     * The options, each by its value, its label, once the catalogues have it,
     * and what the label is given.
     *
     * @return list<array{string, string, array<string, int>}>
     */
    private static function options(RichTextEditor $editor): array
    {
        $translator = new CatalogueTranslator();

        return array_map(static function (array $option) use ($translator): array {
            $translator->trans($option['label'], $option['labelParameters']);

            return [$option['value'], $option['label'], $option['labelParameters']];
        }, $editor->blockOptions());
    }
}
