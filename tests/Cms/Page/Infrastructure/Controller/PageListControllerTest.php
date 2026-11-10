<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the list shows is told by PageListTest, and how the search finds
 * pages by DoctrinePageRepositoryTest; here, that the list is served, and
 * painted, for every shape it takes.
 */
final class PageListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheListWithoutAnyPage(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages');
    }

    /**
     * Every shape the list takes with pages behind it is painted: rows of
     * every kind of page, the pages after the first, and a search, which
     * finds nothing here, as full-text search only sees what is committed.
     */
    #[DataProvider('queries')]
    public function testItOpensTheListOfPages(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $repository = self::getContainer()->get(PageRepository::class);
        $repository->save(PageMother::home(id: PageIdMother::random()->value()));
        $repository->save(PageMother::published(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto', type: PageType::Contact));
        $repository->save(PageMother::create(id: PageIdMother::random()->value(), slug: ''));
        for ($number = 1; $number <= PageSearcher::PAGE_SIZE; ++$number) {
            $repository->save(PageMother::create(id: PageIdMother::random()->value(), title: 'Recurso '.$number, slug: 'recurso-'.$number));
        }

        $client->request('GET', self::URL.$query);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'the first page' => [''];
        yield 'the second page' => ['?page=2'];
        yield 'a page past the end' => ['?page=9'];
        yield 'a search' => ['?title=%20gu%C3%ADa%20'];
    }

    #[DataProvider('invalidPageNumbers')]
    public function testItAnswersNotFoundForAnInvalidPageNumber(string $page): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?page='.$page);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPageNumbers(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'not a number' => ['abc'];
    }
}
