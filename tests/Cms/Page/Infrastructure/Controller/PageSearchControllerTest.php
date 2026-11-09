<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The pages the rich text editor offers to link. The search runs on a FULLTEXT
 * index, which InnoDB only updates on commit, so those tests skip the rollback
 * and tearDown() empties the table.
 */
final class PageSearchControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/search';

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

    #[SkipDatabaseRollback]
    public function testItSearchesThePagesToLinkByTheirTitle(): void
    {
        $client = self::createAuthenticatedClient();
        [$published] = $this->savePages();

        $client->request('GET', self::URL.'?title=sobre');

        self::assertResponseIsSuccessful();
        self::assertSame(['pages' => [[
            'id' => $published,
            'title' => 'Sobre mí',
            'status' => self::statusLabel('published'),
            'published' => true,
        ]], 'more' => false], self::json($client));
    }

    #[SkipDatabaseRollback]
    public function testItListsEveryPageNewestFirstWithoutText(): void
    {
        $client = self::createAuthenticatedClient();
        [$published, $draft] = $this->savePages();

        $client->request('GET', self::URL);

        $pages = self::json($client)['pages'];
        self::assertIsArray($pages);
        self::assertSame(
            [$draft, $published],
            array_map(static fn (mixed $page): mixed => \is_array($page) ? $page['id'] : null, $pages),
            'newest first',
        );
        self::assertSame(
            [self::statusLabel('draft'), self::statusLabel('published')],
            array_map(static fn (mixed $page): mixed => \is_array($page) ? $page['status'] : null, $pages),
        );
    }

    /**
     * The page being edited doesn't link itself.
     */
    #[SkipDatabaseRollback]
    public function testItLeavesOutThePageGiven(): void
    {
        $client = self::createAuthenticatedClient();
        [$published, $draft] = $this->savePages();

        $client->request('GET', self::URL.'?except='.$draft);

        self::assertSame([$published], self::ids($client));
    }

    /**
     * A page with more results after it says so; the last one doesn't.
     */
    #[SkipDatabaseRollback]
    public function testItTellsWhetherThereMayBeMorePages(): void
    {
        $client = self::createAuthenticatedClient();
        $repository = self::getContainer()->get(PageRepository::class);

        for ($index = 1; $index <= PageSearcher::PAGE_SIZE + 1; ++$index) {
            $repository->save(PageMother::create(id: PageIdMother::random()->value(), title: \sprintf('Página %d', $index), slug: \sprintf('pagina-%d', $index)));
        }

        $client->request('GET', self::URL);
        $first = self::json($client);
        $client->request('GET', self::URL.'?page=2');
        $second = self::json($client);

        self::assertTrue($first['more']);
        self::assertCount(PageSearcher::PAGE_SIZE, self::listed($first));
        self::assertFalse($second['more']);
        self::assertCount(1, self::listed($second));
    }

    /**
     * A full page with nothing after it has no more, as the search looks one
     * past it.
     */
    #[SkipDatabaseRollback]
    public function testItTellsAFullLastPageHasNoMore(): void
    {
        $client = self::createAuthenticatedClient();
        $repository = self::getContainer()->get(PageRepository::class);

        for ($index = 1; $index <= PageSearcher::PAGE_SIZE; ++$index) {
            $repository->save(PageMother::create(id: PageIdMother::random()->value(), title: \sprintf('Página %d', $index), slug: \sprintf('pagina-%d', $index)));
        }

        $client->request('GET', self::URL);
        $first = self::json($client);

        self::assertFalse($first['more']);
        self::assertCount(PageSearcher::PAGE_SIZE, self::listed($first));
    }

    public function testItAnswersNotFoundToLeaveOutAnIdThatIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?except=pagina');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItAnswersNotFoundForAnInvalidPageNumber(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * A published page and a draft created after it.
     *
     * @return array{string, string}
     */
    private function savePages(): array
    {
        $published = PageIdMother::random()->value();
        $draft = PageIdMother::random()->value();
        $repository = self::getContainer()->get(PageRepository::class);
        $repository->save(PageMother::published(id: $published, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->save(PageMother::create(id: $draft, title: 'Contacto', slug: 'contacto'));

        return [$published, $draft];
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

    /**
     * @return list<mixed>
     */
    private static function ids(KernelBrowser $client): array
    {
        return array_map(static fn (mixed $page): mixed => \is_array($page) ? $page['id'] : null, self::listed(self::json($client)));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    private static function listed(array $data): array
    {
        self::assertIsList($data['pages']);

        return $data['pages'];
    }

    private static function statusLabel(string $status): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.pages.status.%s.label', $status));
    }
}
