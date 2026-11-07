<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Persistence;

use Cms\Page\Domain\HomeSlug;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\Robots;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Page\Domain\TitleFilter;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Page\Domain\SlugMother;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Full-text tests skip the rollback of dama/doctrine-test-bundle: InnoDB only
 * adds rows to a FULLTEXT index when their transaction commits, so a search
 * inside the test transaction would never see them. tearDown() empties the
 * table instead.
 */
final class DoctrinePageRepositoryTest extends KernelTestCase
{
    /**
     * The one set of pages every assertion runs against. Titles keyed by id,
     * oldest first: page ids are time-ordered UUIDs. Every title has
     * "autismo", so the search also covers a word found in every page.
     */
    private const array PAGES = [
        '01a19e72-b07f-7d78-889c-fa4a14a4fb01' => 'Guías y más guías sobre autismo',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb02' => 'Cómo empezar a entender el autismo',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb03' => 'Guía para familias recién diagnosticadas de autismo',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb04' => 'Guía rápida de autismo',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb05' => 'Yo también soy TEA: autismo en adultos',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb06' => 'Taller de autismo 100% gratis',
        '01a19e72-b07f-7d78-889c-fa4a14a4fb07' => 'Autismo y empleo: guía para empresas',
    ];

    #[\Override]
    protected function tearDown(): void
    {
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM cms_page');

        parent::tearDown();
    }

    public function testItSavesThePageSoItCanBeReadBack(): void
    {
        $id = PageId::random();
        $page = PageMother::create(id: $id->value(), title: 'Sobre mí', slug: 'sobre-mi');
        $entityManager = $this->entityManager();

        $this->repository()->save($page);
        $entityManager->clear();

        self::assertEquals($page, $entityManager->find(Page::class, $id));
    }

    public function testItFindsThePageWithTheGivenId(): void
    {
        $id = PageId::random();
        $page = PageMother::create(id: $id->value(), title: 'Sobre mí', slug: 'sobre-mi');
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $repository->save($page);
        $this->entityManager()->clear();

        self::assertEquals($page, $repository->find($id));
    }

    public function testItFindsNoPageWhenTheIdDoesNotExist(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $this->entityManager()->clear();

        self::assertNull($repository->find(PageId::random()));
    }

    public function testItFindsThePageWithTheGivenSlug(): void
    {
        $page = PageMother::create(id: PageId::random()->value(), title: 'Sobre mí', slug: 'sobre-mi');
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $repository->save($page);
        $this->entityManager()->clear();

        self::assertEquals($page, $repository->findBySlug(SlugMother::create('sobre-mi')));
    }

    public function testItFindsNoPageWhenNoneHasTheSlug(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $this->entityManager()->clear();

        self::assertNull($repository->findBySlug(SlugMother::create('sobre-mi')));
    }

    /**
     * Two saves racing past the availability check: the unique index stops the
     * second one, and the repository reports it as the domain error.
     */
    public function testItRejectsSavingAPageWithTheSlugOfAnotherOne(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'));

        $this->expectExceptionObject(SlugAlreadyTaken::bySlug(SlugMother::create('sobre-mi')));

        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Quién soy', slug: 'sobre-mi'));
    }

    public function testItSavesTheHomePageWithoutASlugSoItCanBeReadBack(): void
    {
        $id = PageId::random();
        $page = PageMother::home(id: $id->value());
        $entityManager = $this->entityManager();

        $this->repository()->save($page);
        $entityManager->clear();

        self::assertEquals($page, $entityManager->find(Page::class, $id));
    }

    public function testItSavesTheTypeOfAUniquePageSoItCanBeReadBack(): void
    {
        $id = PageId::random();
        $page = PageMother::create(id: $id->value(), title: 'Política de cookies', slug: 'politica-cookies', type: PageType::CookiePolicy);
        $entityManager = $this->entityManager();

        $this->repository()->save($page);
        $entityManager->clear();

        self::assertEquals($page, $entityManager->find(Page::class, $id));
    }

    public function testItSavesTheContentAndTheSearchEngineSettingsSoTheyCanBeReadBack(): void
    {
        $id = PageId::random();
        $page = PageMother::create(
            id: $id->value(),
            title: 'Política de cookies',
            slug: 'politica-cookies',
            content: Text::stored(
                Text::heading(2, 'Qué cookies usamos'),
                Text::paragraph('Solo usamos cookies ', '**técnicas**', '.'),
                Text::heading(3, 'Detalle'),
                ['type' => 'quote', 'paragraphs' => [Text::text('Una cita')]],
                ['type' => 'list', 'style' => 'ordered', 'items' => [Text::text('Sesión'), Text::text('Idioma')]],
                Text::callout('important', 'Puedes borrarlas desde tu navegador.'),
                Text::table(['Nombre', 'Finalidad'], [['PHPSESSID', '']]),
            ),
            metaDescription: 'Qué cookies usa el blog y para qué.',
            robots: Robots::NoindexFollow,
        );
        $entityManager = $this->entityManager();

        $this->repository()->save($page);
        $entityManager->clear();

        self::assertEquals($page, $entityManager->find(Page::class, $id));
    }

    public function testItFindsTheHomePageByItsEmptySlug(): void
    {
        $home = PageMother::home(id: PageId::random()->value());
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $repository->save($home);
        $this->entityManager()->clear();

        self::assertEquals($home, $repository->findBySlug(new HomeSlug()));
    }

    public function testItFindsNoPageAtTheRootWithoutAHomePage(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto'));
        $this->entityManager()->clear();

        self::assertNull($repository->findBySlug(new HomeSlug()));
    }

    public function testItFindsThePageOfTheGivenType(): void
    {
        $contact = PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto', type: PageType::Contact);
        $repository = $this->repository();
        $repository->save(PageMother::home(id: PageId::random()->value()));
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->save($contact);
        $this->entityManager()->clear();

        self::assertEquals($contact, $repository->findByType(PageType::Contact));
    }

    public function testItFindsNoPageWhenNoneHasTheType(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::home(id: PageId::random()->value()));
        $repository->save(PageMother::create(id: PageId::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'));
        $this->entityManager()->clear();

        self::assertNull($repository->findByType(PageType::Contact));
    }

    /**
     * The home page is the only one stored with an empty slug, so the unique
     * index also keeps the site to a single home page.
     */
    public function testItRejectsSavingASecondHomePage(): void
    {
        $repository = $this->repository();
        $repository->save(PageMother::home(id: PageId::random()->value()));

        $this->expectExceptionObject(SlugAlreadyTaken::bySlug(new HomeSlug()));

        $repository->save(PageMother::home(id: PageId::random()->value(), title: 'Otra portada'));
    }

    public function testItDeletesOnlyTheGivenPage(): void
    {
        $id = PageId::random();
        $kept = PageMother::create(id: PageId::random()->value(), title: 'Contacto', slug: 'contacto');
        $repository = $this->repository();
        $repository->save($kept);
        $repository->save(PageMother::create(id: $id->value(), title: 'Sobre mí', slug: 'sobre-mi'));
        $entityManager = $this->entityManager();
        $entityManager->clear();

        $repository->delete($repository->find($id) ?? self::fail('The page to delete was not saved.'));
        $entityManager->clear();

        self::assertNull($repository->find($id));
        self::assertEquals($kept, $repository->find($kept->id()));
    }

    /**
     * The only test that commits its data, which full-text search needs: it
     * saves the pages once and checks every kind of criteria against them, so
     * the suite pays for one committed fixture instead of one per case.
     */
    #[SkipDatabaseRollback]
    public function testItFindsThePagesMatchingTheCriteria(): void
    {
        $this->savePages();
        $repository = $this->repository();

        self::assertEqualsCanonicalizing(
            array_values(self::PAGES),
            $this->titles($repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: null, offset: 0))),
            'without criteria, every page',
        );

        self::assertEquals(
            [PageMother::create(id: '01a19e72-b07f-7d78-889c-fa4a14a4fb04', title: 'Guía rápida de autismo', slug: 'pagina-4')],
            $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [TitleFilter::matches('rápida')], order: null, offset: 0)),
            'pages come back whole: id, title and slug',
        );

        foreach (self::criteriaCases() as $case => [$criteria, $expected]) {
            self::assertSame($expected, $this->titles($repository->matching($criteria)), $case);
        }
    }

    public function testItFindsNothingWhenThereAreNoPages(): void
    {
        $repository = $this->repository();

        self::assertSame([], $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: null, offset: 0)), 'without criteria');
        self::assertSame([], $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [TitleFilter::matches('guía')], order: null, offset: 0)), 'searching the title');
    }

    public function testItRejectsSortingByAFieldItDoesNotExpose(): void
    {
        $this->expectExceptionObject(InvalidCriteria::unknownField('createdAt'));

        $this->repository()->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('createdAt', OrderDirection::Ascending), offset: 0));
    }

    /**
     * Expected titles for each criteria, in the order they must come back.
     *
     * @return iterable<string, array{Criteria, list<string>}>
     */
    private static function criteriaCases(): iterable
    {
        $oldestFirst = array_values(self::PAGES);
        $alphabetical = [
            'Autismo y empleo: guía para empresas',
            'Cómo empezar a entender el autismo',
            'Guía para familias recién diagnosticadas de autismo',
            'Guía rápida de autismo',
            'Guías y más guías sobre autismo',
            'Taller de autismo 100% gratis',
            'Yo también soy TEA: autismo en adultos',
        ];
        $byId = new Order('id', OrderDirection::Ascending);
        $newestFirst = new Order('id', OrderDirection::Descending);

        // Sorting.
        yield 'sorted by id, oldest first' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $byId, offset: 0), $oldestFirst];
        yield 'sorted by id, newest first' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $newestFirst, offset: 0), array_reverse($oldestFirst)];
        yield 'sorted by title, ignoring accents' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('title', OrderDirection::Ascending), offset: 0), $alphabetical];
        yield 'sorted by title, descending' => [
            new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('title', OrderDirection::Descending), offset: 0),
            array_reverse($alphabetical),
        ];
        yield 'sorted by slug' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('slug', OrderDirection::Ascending), offset: 0), $oldestFirst];

        // Pagination.
        yield 'first page' => [new Criteria(limit: 3, filters: [], order: $byId, offset: 0), \array_slice($oldestFirst, 0, 3)];
        yield 'middle page' => [new Criteria(limit: 3, filters: [], order: $byId, offset: 3), \array_slice($oldestFirst, 3, 3)];
        yield 'last, incomplete page' => [new Criteria(limit: 3, filters: [], order: $byId, offset: 6), \array_slice($oldestFirst, 6)];
        yield 'page past the end' => [new Criteria(limit: 3, filters: [], order: $byId, offset: 9), []];
        yield 'limit larger than the results' => [new Criteria(limit: 50, filters: [], order: $byId, offset: 0), $oldestFirst];
        yield 'limit of one' => [new Criteria(limit: 1, filters: [], order: $byId, offset: 0), \array_slice($oldestFirst, 0, 1)];

        // Full-text search on the title: by relevance first, newest first on ties.
        $search = static fn (string $text, int $limit = Criteria::MAX_LIMIT, int $offset = 0): Criteria => new Criteria(
            $limit,
            [TitleFilter::matches($text)],
            $newestFirst,
            $offset,
        );
        $guides = [
            'Guías y más guías sobre autismo',
            'Autismo y empleo: guía para empresas',
            'Guía rápida de autismo',
            'Guía para familias recién diagnosticadas de autismo',
        ];

        yield 'search: a single word' => [$search('rápida'), ['Guía rápida de autismo']];
        yield 'search: ignoring case and accents' => [$search('CÓMO'), ['Cómo empezar a entender el autismo']];
        yield 'search: without the accents of the title' => [$search('como'), ['Cómo empezar a entender el autismo']];
        yield 'search: every word, in any order' => [$search('familias guía'), ['Guía para familias recién diagnosticadas de autismo']];
        yield 'search: words as prefixes' => [$search('famil diagn'), ['Guía para familias recién diagnosticadas de autismo']];
        yield 'search: every word is required' => [$search('guía inexistente'), []];
        yield 'search: no page has the word' => [$search('inexistente'), []];
        yield 'search: more occurrences rank higher, ties newest first' => [$search('guía'), $guides];
        yield 'search: words shorter than three letters are ignored' => [$search('yo tea'), ['Yo también soy TEA: autismo en adultos']];
        yield 'search: only short words find nothing' => [$search('yo'), []];
        yield 'search: numbers are words' => [$search('100%'), ['Taller de autismo 100% gratis']];
        yield 'search: like wildcards are not wildcards' => [$search('%'), []];
        yield 'search: boolean operators are not interpreted' => [$search('+-()*"~<>@'), []];
        yield 'search: operators glued to words are dropped' => [$search('-rápida'), ['Guía rápida de autismo']];
        yield 'search: a word present in every page, scored close to zero by InnoDB' => [
            $search('autismo'),
            array_reverse($oldestFirst),
        ];
        yield 'search: paginated' => [$search('guía', 2, 1), \array_slice($guides, 1, 2)];
    }

    /**
     * @param array<string, string> $titles titles keyed by page id
     */
    private function savePages(array $titles = self::PAGES): void
    {
        $repository = $this->repository();
        $number = 0;

        foreach ($titles as $id => $title) {
            $repository->save(PageMother::create(id: $id, title: $title, slug: \sprintf('pagina-%d', ++$number)));
        }

        $this->entityManager()->clear();
    }

    /**
     * @param list<Page> $pages
     *
     * @return list<string>
     */
    private function titles(array $pages): array
    {
        return array_map(static fn (Page $page): string => $page->title()->value(), $pages);
    }

    private function repository(): PageRepository
    {
        return self::getContainer()->get(PageRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
