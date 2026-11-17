<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\SearchTerms;
use Web\Post\Domain\SearchTooBroad;
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

    /**
     * Words so common the search would take longer than it may are answered
     * as any other search, asking for another word, and kept as long.
     */
    public function testItAnswersASearchTooBroadAsAnyOther(): void
    {
        $client = self::createClient();
        $this->publishPages();
        $posts = self::createStub(PostRepository::class);
        $posts->method('searched')->willThrowException(SearchTooBroad::forTerms(SearchTerms::fromText('ansiedad')));
        self::getContainer()->set(PostRepository::class, $posts);

        $client->request('GET', self::HOST.'/buscar.html?q=ansiedad');

        self::assertResponseIsSuccessful();
        self::assertSame('max-age=0, public, s-maxage=300', $client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * A reader may search twenty times a minute, whatever they search; past
     * that they are told to wait, and that answer is kept by no cache. The
     * page alone, searching nothing, never counts.
     */
    public function testItAsksAReaderWhoSearchesTooOftenToWait(): void
    {
        $client = self::createClient();
        // One kernel for every request, as the limiter keeps its counts across them.
        $client->disableReboot();
        $this->publishPages();

        for ($search = 1; $search <= 20; ++$search) {
            $client->request('GET', self::HOST.'/buscar.html?q=palabra'.$search);
            self::assertResponseIsSuccessful();
        }

        $client->request('GET', self::HOST.'/buscar.html');
        self::assertResponseIsSuccessful();

        $client->request('GET', self::HOST.'/buscar.html?q=otra');
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertStringNotContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
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
