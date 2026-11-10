<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\SearchPreview;

/**
 * How search results show a page: its title and its description cut where
 * results cut them, with an ellipsis, and a description to write when it
 * has none.
 */
final class SearchPreviewTest extends TestCase
{
    #[DataProvider('titles')]
    public function testItCutsTheTitleWhereResultsCutIt(string $title, string $shown): void
    {
        self::assertSame($shown, self::preview($title, 'Resumen')->shownTitle());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function titles(): iterable
    {
        yield 'a short one' => ['Sobre mí', 'Sobre mí'];
        yield 'one as long as it can be' => [str_repeat('á', SearchPreview::TITLE_LIMIT), str_repeat('á', SearchPreview::TITLE_LIMIT)];
        yield 'one longer' => [str_repeat('á', SearchPreview::TITLE_LIMIT + 1), str_repeat('á', SearchPreview::TITLE_LIMIT - 1).'…'];
        yield 'one cut after a space' => [str_repeat('a', SearchPreview::TITLE_LIMIT - 2).' bb', str_repeat('a', SearchPreview::TITLE_LIMIT - 2).'…'];
    }

    #[DataProvider('descriptions')]
    public function testItCutsTheDescriptionWhereResultsCutIt(string $description, string $shown): void
    {
        self::assertSame($shown, self::preview('Sobre mí', $description)->shownDescription());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function descriptions(): iterable
    {
        yield 'none, as yet to write' => ['', 'Sin descripción'];
        yield 'a short one' => ['Quién escribe el blog.', 'Quién escribe el blog.'];
        yield 'one as long as it can be' => [str_repeat('é', SearchPreview::DESCRIPTION_LIMIT), str_repeat('é', SearchPreview::DESCRIPTION_LIMIT)];
        yield 'one longer' => [str_repeat('é', SearchPreview::DESCRIPTION_LIMIT + 1), str_repeat('é', SearchPreview::DESCRIPTION_LIMIT - 1).'…'];
    }

    private static function preview(string $title, string $description): SearchPreview
    {
        $preview = new SearchPreview();
        $preview->mount('Vista previa', $title, 'https://www.blogautista.localhost/sobre-mi.html', 'https://www.blogautista.localhost', $description, 'Sin descripción', ['title' => 'page-title', 'description' => 'page-meta-description']);

        return $preview;
    }
}
