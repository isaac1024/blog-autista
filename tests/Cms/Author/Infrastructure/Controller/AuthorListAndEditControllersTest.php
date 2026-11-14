<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorSearcher;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Listing, creating and opening the authors of the CMS.
 */
final class AuthorListAndEditControllersTest extends AuthorControllerTestCase
{
    #[DataProvider('pages')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        yield 'the list' => ['/authors'];
        yield 'an editor' => ['/authors/'.AuthorIdMother::DEFAULT_VALUE];
    }

    #[DataProvider('queries')]
    public function testItOpensTheListOfAuthors(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveAuthor(AuthorMother::published(id: AuthorIdMother::random()->value(), slug: 'publicado'));
        $this->saveAuthor(AuthorMother::published(id: AuthorIdMother::random()->value(), slug: 'cambiado', publishedName: 'Antes'));
        $this->saveAuthor(AuthorMother::create(id: AuthorIdMother::random()->value(), slug: ''));
        for ($number = 1; $number <= AuthorSearcher::PAGE_SIZE; ++$number) {
            $this->saveAuthor(AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Autor '.$number, slug: 'autor-'.$number));
        }

        $client->request('GET', self::HOST.'/authors'.$query);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_authors');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'the first page' => [''];
        yield 'the second page' => ['?page=2'];
        yield 'a search that finds some' => ['?name=autor'];
        yield 'a search that finds none' => ['?name=nadie'];
    }

    public function testItOpensTheEmptyList(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/authors');

        self::assertResponseIsSuccessful();
    }

    public function testItAnswersNotFoundForAPageBeforeTheFirst(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/authors?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItCreatesTheAuthorAndOpensItInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_authors_create', self::HOST.'/authors', ['name' => 'Isaac Abreu']);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/authors/[0-9a-f-]{36}$#', $location);
        self::assertSame([['key' => 'cms.notices.author.created', 'parameters' => []]], self::successNotices($client));
        self::assertSame('isaac-abreu', self::getContainer()->get(AuthorRepository::class)->find(new AuthorId(basename($location)))?->slug()?->value());
    }

    public function testItGoesBackToTheListWhenTheAuthorIsRefused(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_authors_create', self::HOST.'/authors', ['name' => 'Al']);

        self::assertResponseRedirects('/authors', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.author.name.too_short', 'parameters' => ['%min%' => 3]]], self::errorNotices($client));
    }

    #[DataProvider('authors')]
    public function testItOpensTheEditorOfTheAuthor(Author $author): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveAuthor($author);

        $client->request('GET', self::HOST.'/authors/'.$author->id()->value());

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_authors_edit', ['id' => $author->id()->value()]);
    }

    /**
     * @return iterable<string, array{Author}>
     */
    public static function authors(): iterable
    {
        yield 'a draft without an avatar' => [AuthorMother::create(id: AuthorIdMother::random()->value(), slug: '')];
        yield 'a draft with an avatar' => [AuthorMother::publishable(id: AuthorIdMother::random()->value())];
        yield 'a published author with changes' => [AuthorMother::published(id: AuthorIdMother::random()->value(), publishedName: 'Antes')];
        yield 'an unpublished author' => [AuthorMother::unpublished(id: AuthorIdMother::random()->value())];
    }

    public function testItRespondsNotFoundWhenTheAuthorDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/authors/'.AuthorIdMother::random()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
