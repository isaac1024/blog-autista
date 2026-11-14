<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The authors a field picks one of, such as where a redirect leads:
 * searched by name a page at a time, and looked up by their ids, as JSON,
 * each named as the title of what is picked.
 */
final class AuthorPickerControllersTest extends AuthorControllerTestCase
{
    private const string OLDER = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1f01';
    private const string NEWER = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1f02';

    #[DataProvider('endpoints')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpoints(): iterable
    {
        yield 'the search' => ['/authors/search'];
        yield 'the lookup' => ['/authors/lookup'];
    }

    public function testItFindsTheAuthorsWhoseNameHasWhatIsSearchedNewestFirst(): void
    {
        $client = $this->clientWithAuthors();

        $client->request('GET', self::HOST.'/authors/search');
        self::assertResponseIsSuccessful();
        self::assertSame(['authors' => [self::picked(self::NEWER, 'Ana', 'published', true), self::picked(self::OLDER, AuthorMother::NAME, 'draft', false)], 'more' => false], self::json($client));
    }

    public function testItFindsOnlyTheAuthorsWhoseNameHasWhatIsSearched(): void
    {
        $client = $this->clientWithAuthors();

        $client->request('GET', self::HOST.'/authors/search?name=ana');

        self::assertSame(['authors' => [self::picked(self::NEWER, 'Ana', 'published', true)], 'more' => false], self::json($client));
    }

    public function testItAnswersNotFoundForAPageOfResultsThatCannotBe(): void
    {
        $client = $this->clientWithAuthors();

        $client->request('GET', self::HOST.'/authors/search?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * An id of no author, or no id at all, is left out.
     */
    public function testItLooksUpTheAuthorsWithTheIdsAsked(): void
    {
        $client = $this->clientWithAuthors();

        $client->request('GET', self::HOST.'/authors/lookup?ids='.implode(',', [self::NEWER, AuthorIdMother::random()->value(), 'autor', '']));

        self::assertResponseIsSuccessful();
        self::assertSame(['authors' => [self::picked(self::NEWER, 'Ana', 'published', true)]], self::json($client));
    }

    private function clientWithAuthors(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $this->saveAuthor(AuthorMother::create(id: self::OLDER));
        $this->saveAuthor(AuthorMother::published(id: self::NEWER, name: 'Ana', slug: 'ana'));

        return $client;
    }

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    private static function picked(string $id, string $name, string $status, bool $published): array
    {
        $label = self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.authors.status.%s.label', $status));

        return ['id' => $id, 'title' => $name, 'status' => $label, 'published' => $published];
    }

    /**
     * @return array<mixed>
     */
    private static function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
