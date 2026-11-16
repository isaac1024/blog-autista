<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Shared\Domain\Robots;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

/**
 * The page that searches the posts answers whatever is searched, a page of
 * results at a time; a page of results that can't be isn't there. Other
 * pages take no notice of what their query asks.
 */
final class SearchPageTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    #[DataProvider('searches')]
    public function testItAnswersEverySearch(string $query): void
    {
        $client = self::createClient();
        $this->publishPages();

        $client->request('GET', self::HOST.'/buscar.html'.$query);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('web_page', ['slug' => 'buscar']);
        // Each search is the same for every reader, so the page cache keeps it a while.
        self::assertSame('max-age=0, public, s-maxage=300', $client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function searches(): iterable
    {
        yield 'nothing searched' => [''];
        yield 'words found nowhere' => ['?q=elefante'];
        yield 'words too short' => ['?q=no+y'];
        yield 'the first page, numbered' => ['?q=ruido&pagina=1'];
    }

    #[DataProvider('pagesThatCannotBe')]
    public function testItHasNoPageOfResultsThatCannotBe(string $query): void
    {
        $client = self::createClient();
        $this->publishPages();

        $client->request('GET', self::HOST.'/buscar.html'.$query);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesThatCannotBe(): iterable
    {
        yield 'past the last' => ['?q=elefante&pagina=2'];
        yield 'of nothing searched' => ['?pagina=2'];
        yield 'before the first' => ['?q=ruido&pagina=0'];
        yield 'not a number' => ['?q=ruido&pagina=dos'];
        yield 'a number written otherwise' => ['?q=ruido&pagina=01'];
    }

    public function testItLeavesTheQueryOfAnyOtherPageAlone(): void
    {
        $client = self::createClient();
        $this->publishPages();

        $client->request('GET', self::HOST.'/sobre-mi.html?q=ruido&pagina=dos');

        self::assertResponseIsSuccessful();
    }

    private function publishPages(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(Page::publish(CmsPagePublishedMother::create()));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Buscar', slug: 'buscar', type: PageType::Search, robots: Robots::NoindexFollow)));
    }
}
