<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteErrorPage;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * The page of an address the site doesn't answer tells whether it never
 * did or no longer does, in its title too, and leads home.
 */
final class SiteErrorPageTest extends TestCase
{
    #[DataProvider('reasons')]
    public function testItTellsWhyTheAddressAnswersNothing(bool $gone, string $reason): void
    {
        $errorPage = new SiteErrorPage(new CatalogueTranslator());

        $page = $errorPage->paint(Page::create($errorPage->title($gone)), $gone, '/');
        ComponentContracts::assertHonoured($page);

        self::assertSame(\sprintf('site.%s.title', $reason), $page->title);
        PageComponents::one($page, 'Shared:PageTitle', ['title' => \sprintf('site.%s.title', $reason), 'subtitle' => \sprintf('site.%s.message', $reason)]);
        PageComponents::one($page, 'Shared:ButtonLink', ['url' => '/']);
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function reasons(): iterable
    {
        yield 'never answered' => [false, 'not_found'];
        yield 'no longer answered' => [true, 'gone'];
    }
}
