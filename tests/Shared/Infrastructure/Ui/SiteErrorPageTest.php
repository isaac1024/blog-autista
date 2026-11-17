<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\SiteError;
use Shared\Infrastructure\Ui\SiteErrorPage;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * The page of an address the site doesn't answer tells whether it never
 * did, no longer does or failed to, in its title too, and leads home.
 */
final class SiteErrorPageTest extends TestCase
{
    #[DataProvider('reasons')]
    public function testItTellsWhyTheAddressAnswersNothingUnderADrawingOfIt(int $statusCode, string $reason): void
    {
        $page = $this->paint($statusCode, '/algo.html');

        self::assertSame(\sprintf('site.%s.title', $reason), $page->title);
        PageComponents::one($page, 'Shared:ErrorIllustration', ['error' => $reason]);
        PageComponents::one($page, 'Shared:PageTitle', ['title' => \sprintf('site.%s.title', $reason), 'subtitle' => \sprintf('site.%s.message', $reason)]);
    }

    /**
     * Where nothing is, the reader may search for it, by the words of the
     * address, or read the latest posts, or go home.
     */
    public function testItOffersToSearchByTheWordsOfTheAddressTheLatestPostsAndHome(): void
    {
        $latest = [['url' => '/vida-diaria/2026/11/15/metro.html', 'title' => 'Metro', 'excerpt' => null, 'section' => 'Vida diaria', 'date' => '15 de noviembre de 2026', 'datetime' => '2026-11-15T10:00:00+01:00', 'image' => null]];

        foreach ([404, 410] as $statusCode) {
            PageComponents::one($this->paint($statusCode, '/autismo/2026/11/15/sobrecarga-sensorial.html', '/buscar.html', $latest), 'Shared:SiteErrorHelp', [
                'searchUrl' => '/buscar.html',
                'searchName' => 'q',
                'words' => 'autismo sobrecarga sensorial',
                'latest' => $latest,
                'homeUrl' => '/',
            ]);
        }
    }

    /**
     * Its words are runs of letters long enough to search by, lowercase,
     * each once, as the address writes them: never its extension, nor the
     * numbers of a day, nor what is too short to find anything.
     */
    #[DataProvider('addresses')]
    public function testItTakesTheWordsOfTheAddress(string $path, string $words): void
    {
        PageComponents::one($this->paint(404, $path), 'Shared:SiteErrorHelp', ['words' => $words, 'searchUrl' => null]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'a post' => ['/autismo/2026/11/15/sobrecarga-sensorial.html', 'autismo sobrecarga sensorial'];
        yield 'written by the browser' => ['/cami%C3%B3n-de-la-ma%C3%B1ana.html', 'camión mañana'];
        yield 'a word twice, in capitals' => ['/Ruido/ruido-y-calma/', 'ruido calma'];
        yield 'nothing to search by' => ['/a/1/de.php', ''];
    }

    /**
     * Where the site failed, the reader may try the same address again, or
     * go home; nothing more is read, as what failed may be what it would read.
     */
    public function testItOffersToTryTheAddressAgainWhereTheSiteFailed(): void
    {
        $page = $this->paint(503, '/vida-diaria/');

        self::assertSame(
            [['url' => '/vida-diaria/', 'label' => 'site.retry'], ['url' => '/', 'label' => 'site.home']],
            array_map(static fn (PageComponent $button): array => $button->props, PageComponents::find($page, 'Shared:ButtonLink', [])),
        );
        self::assertSame([], PageComponents::find($page, 'Shared:SiteErrorHelp', []));
    }

    /**
     * Of the client errors only gone tells something more than that the
     * site has nothing for it; every error of the server is a failure.
     *
     * @return iterable<string, array{int, string}>
     */
    public static function reasons(): iterable
    {
        yield 'never answered' => [404, 'not_found'];
        yield 'forbidden' => [403, 'not_found'];
        yield 'asked the wrong way' => [405, 'not_found'];
        yield 'the last client error' => [499, 'not_found'];
        yield 'no longer answered' => [410, 'gone'];
        yield 'failed' => [500, 'server_error'];
        yield 'unavailable' => [503, 'server_error'];
    }

    /**
     * @param list<array{url: string, title: string, excerpt: ?string, section: string, date: string, datetime: string, image: ?array{src: string, srcset: string, width: int, height: int}}> $latest
     */
    private function paint(int $statusCode, string $path, ?string $searchUrl = null, array $latest = []): Page
    {
        $errorPage = new SiteErrorPage(new CatalogueTranslator());
        $error = SiteError::fromStatusCode($statusCode);
        $page = $errorPage->paint(Page::create($errorPage->title($error)), $error, '/', $path, $searchUrl, 'q', $latest);
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
