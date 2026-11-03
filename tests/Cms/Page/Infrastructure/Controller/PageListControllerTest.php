<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The page list with real pages behind it. The search runs on a FULLTEXT
 * index, which InnoDB only updates on commit, so the test that searches skips
 * the rollback of dama/doctrine-test-bundle and tearDown() empties the table.
 */
final class PageListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages';

    #[\Override]
    protected function tearDown(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('DELETE FROM cms_page');

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItInvitesToCreateTheFirstPageWhenThereAreNone(): void
    {
        $client = self::createAuthenticatedClient();

        $crawler = $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages');
        self::assertSame([], $this->cardTitles($crawler));
        self::assertSame($this->trans('cms.pages.empty.title'), $this->emptyStateTitle($crawler));
    }

    /**
     * The only test that commits its data: it saves the pages once and requests
     * the list with every kind of query against them.
     */
    #[SkipDatabaseRollback]
    public function testItListsThePagesForEveryQuery(): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePages();

        $newestFirst = array_reverse(self::pages());
        $guides = [
            'Guías y más guías sobre autismo',
            'Autismo y empleo: guía para empresas',
            'Guía rápida de autismo',
            'Guía para familias recién diagnosticadas de autismo',
        ];

        foreach ([
            'first page by default, newest first' => ['', \array_slice($newestFirst, 0, 20)],
            'first page asked for explicitly' => ['?page=1', \array_slice($newestFirst, 0, 20)],
            'second page has the rest' => ['?page=2', \array_slice($newestFirst, 20)],
            'blank title does not filter' => ['?title=%20%20', \array_slice($newestFirst, 0, 20)],
            'search by title, most relevant first' => ['?title=gu%C3%ADa', $guides],
            'search ignoring case and accents' => ['?title=GUIA', $guides],
            'search with surrounding spaces' => ['?title=%20gu%C3%ADa%20', $guides],
            'search every word, in any order' => ['?title=familias+gu%C3%ADa', ['Guía para familias recién diagnosticadas de autismo']],
            'search with many results, newest first' => ['?title=recurso', \array_slice($newestFirst, 0, 14)],
        ] as $case => [$query, $expected]) {
            $crawler = $client->request('GET', self::URL.$query);

            self::assertResponseIsSuccessful($case);
            self::assertSame($expected, $this->cardTitles($crawler), $case);
        }

        foreach ([
            'page past the end' => ['?page=3', 'cms.pages.empty.title'],
            'search without results' => ['?title=inexistente', 'cms.pages.search.empty.title'],
            'search past its last page' => ['?title=gu%C3%ADa&page=2', 'cms.pages.search.empty.title'],
        ] as $case => [$query, $emptyState]) {
            $crawler = $client->request('GET', self::URL.$query);

            self::assertResponseIsSuccessful($case);
            self::assertSame([], $this->cardTitles($crawler), $case);
            self::assertSame($this->trans($emptyState), $this->emptyStateTitle($crawler), $case);
        }

        $crawler = $client->request('GET', self::URL.'?title=%20gu%C3%ADa%20');
        self::assertSame('guía', $crawler->filter('#page-search')->attr('value'), 'the search box keeps the trimmed text');
    }

    public function testItAnswersNotFoundForAnInvalidPageNumber(): void
    {
        $client = self::createAuthenticatedClient();

        foreach (['0', '-1', 'abc'] as $page) {
            $client->request('GET', self::URL.'?page='.$page);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, \sprintf('page=%s', $page));
        }
    }

    /**
     * Keeps the client's kernel between requests so the fixture and the
     * requests share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * Titles oldest first: seven pages to search by words and fourteen more,
     * so there is a second page of twenty.
     *
     * @return list<string>
     */
    private static function pages(): array
    {
        $titles = [
            'Guías y más guías sobre autismo',
            'Cómo empezar a entender el autismo',
            'Guía para familias recién diagnosticadas de autismo',
            'Guía rápida de autismo',
            'Yo también soy TEA: autismo en adultos',
            'Taller de autismo 100% gratis',
            'Autismo y empleo: guía para empresas',
        ];

        for ($number = 1; $number <= 14; ++$number) {
            $titles[] = \sprintf('Recurso de apoyo %d', $number);
        }

        return $titles;
    }

    /**
     * Saves the pages with time-ordered ids, oldest first.
     */
    private function savePages(): void
    {
        $repository = self::getContainer()->get(PageRepository::class);

        foreach (self::pages() as $index => $title) {
            $repository->save(PageMother::create(
                id: \sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fb%02d', $index + 1),
                title: $title,
                slug: \sprintf('pagina-%d', $index + 1),
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function cardTitles(Crawler $crawler): array
    {
        return $crawler->filter('main article h2')->each(static fn (Crawler $title): string => $title->text());
    }

    private function emptyStateTitle(Crawler $crawler): ?string
    {
        // The empty state's heading is the only one outside the modal and the cards.
        $title = $crawler->filter('main h2')->reduce(static fn (Crawler $node): bool => null === $node->closest('dialog, article'));

        return 0 === $title->count() ? null : $title->text();
    }

    private function trans(string $key): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans($key);
    }
}
