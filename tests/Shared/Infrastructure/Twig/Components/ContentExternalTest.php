<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\ContentExternal;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * What the card of an external resource tells under its title: where it
 * comes from, and when, as each kind tells it.
 */
final class ContentExternalTest extends TestCase
{
    /**
     * @param array<string, mixed> $details
     * @param list<string>         $source
     */
    #[DataProvider('sources')]
    public function testItTellsWhereEachKindComesFrom(string $kind, string $url, array $details, array $source): void
    {
        $card = new ContentExternal(self::translator());
        $card->mount($kind, $url, 'Un recurso', 'De qué trata.', 'es', $details, null, false);

        self::assertSame($source, $card->source);
        self::assertSame([$kind, $url, 'Un recurso', 'De qué trata.', 'es', null], [$card->kind, $card->url, $card->title, $card->summary, $card->language, $card->image]);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, list<string>}>
     */
    public static function sources(): iterable
    {
        $page = 'https://www.example.org/noticia.html';

        yield 'an article, by its publisher, on its day' => ['article', $page, ['publisher' => 'El Medio', 'published_on' => '2026-11-01'], ['El Medio', '1 de noviembre de 2026']];
        yield 'an article whose publisher is unknown, by its host' => ['article', $page, ['publisher' => '', 'published_on' => null], ['example.org']];
        yield 'an article of a day that is none' => ['article', $page, ['publisher' => 'El Medio', 'published_on' => '1 de noviembre'], ['El Medio']];
        yield 'a study, by its journal, year and authors' => ['study', 'https://doi.org/10.1038/nature12373', ['journal' => 'Nature', 'year' => 2013, 'authors' => 'G. Kucsko'], ['Nature', '2013', 'G. Kucsko']];
        yield 'a study of no year' => ['study', 'https://doi.org/10.1038/nature12373', ['journal' => 'Nature', 'year' => '2013', 'authors' => null], ['Nature']];
        yield 'a document, by its publisher, size and pages' => ['document', 'https://www.boe.es/informe.pdf', ['publisher' => 'BOE', 'bytes' => 2_345_678, 'pages' => 12], ['BOE', 'content.external.size(%size%=2,3)', 'content.external.pages(%count%=12)']];
        yield 'a small document of one page, at least a tenth of a megabyte' => ['document', 'https://www.boe.es/informe.pdf', ['publisher' => null, 'bytes' => 16, 'pages' => 1], ['content.external.size(%size%=0,1)', 'content.external.pages(%count%=1)']];
        yield 'a document that tells neither its size nor its pages' => ['document', 'https://www.boe.es/informe.pdf', ['publisher' => 'BOE', 'bytes' => null, 'pages' => '12'], ['BOE']];
        yield 'an address without a host' => ['article', 'mailto:alguien@example.org', [], []];
    }

    private static function translator(): CatalogueTranslator
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        return $translator;
    }
}
