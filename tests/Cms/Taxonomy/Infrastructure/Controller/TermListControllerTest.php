<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermSearcher;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class TermListControllerTest extends TermControllerTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.'/sections');

        self::assertResponseRedirects(self::HOST.'/login');
    }

    #[DataProvider('taxonomies')]
    public function testItOpensTheListWithoutAnyTerm(string $segment): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/'.$segment);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_terms', ['taxonomy' => $segment]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function taxonomies(): iterable
    {
        yield 'the sections' => ['sections'];
        yield 'the tags' => ['tags'];
    }

    /**
     * Every shape the list takes with terms behind it is painted: rows of
     * every state, the pages after the first, and searches that find some
     * and none.
     */
    #[DataProvider('queries')]
    public function testItOpensTheListOfTerms(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveTerm(TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: 'publicada'));
        $this->saveTerm(TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: 'cambiada', publishedName: 'Antes'));
        $this->saveTerm(TermMother::unpublished(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: 'retirada'));
        $this->saveTerm(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: ''));
        for ($number = 1; $number <= TermSearcher::PAGE_SIZE; ++$number) {
            $this->saveTerm(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta '.$number, slug: 'etiqueta-'.$number));
        }

        $client->request('GET', self::HOST.'/tags'.$query);

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
        yield 'a search that finds some' => ['?name=%20etiqueta%20'];
        yield 'a search that finds none' => ['?name=nada'];
    }

    #[DataProvider('invalidAddresses')]
    public function testItAnswersNotFoundForAnInvalidAddress(string $path): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'a page before the first' => ['/sections?page=0'];
        yield 'a page that is no number' => ['/tags?page=abc'];
        yield 'a taxonomy the CMS has not' => ['/categories'];
    }
}
