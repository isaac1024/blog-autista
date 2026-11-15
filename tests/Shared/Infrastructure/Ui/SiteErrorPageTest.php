<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
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
    public function testItTellsWhyTheAddressAnswersNothing(int $statusCode, string $reason): void
    {
        $errorPage = new SiteErrorPage(new CatalogueTranslator());
        $error = SiteError::fromStatusCode($statusCode);

        $page = $errorPage->paint(Page::create($errorPage->title($error)), $error, '/');
        ComponentContracts::assertHonoured($page);

        self::assertSame(\sprintf('site.%s.title', $reason), $page->title);
        PageComponents::one($page, 'Shared:PageTitle', ['title' => \sprintf('site.%s.title', $reason), 'subtitle' => \sprintf('site.%s.message', $reason)]);
        PageComponents::one($page, 'Shared:ButtonLink', ['url' => '/']);
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
}
