<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsResponse;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorName;
use Cms\Author\Infrastructure\Controller\AuthorList;
use Cms\Author\Infrastructure\Controller\AuthorState;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the list of authors makes: what it lists, what it says when
 * there is nothing to list, and where each author and each search leads.
 */
final class AuthorListTest extends TestCase
{
    private const string ID = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    public function testItCreatesAnAuthorFromItsName(): void
    {
        $list = $this->list([], '', 1, false);

        PageComponents::one($list, 'Shared:PageTitle', ['title' => 'cms.authors.title', 'subtitle' => 'cms.authors.subtitle']);
        PageComponents::one($list, 'Shared:Modal', ['trigger' => 'cms.authors.add', 'title' => 'cms.authors.modal.title', 'description' => 'cms.authors.modal.description']);
        $form = PageComponents::one($list, 'Shared:Form', ['action' => '/cms_authors_create', 'csrfTokenId' => 'cms_authors_create']);
        $name = PageComponents::one($form, 'Shared:FormInput', ['name' => 'name']);
        self::assertSame(['required' => true, 'minlength' => AuthorName::MIN_LENGTH, 'maxlength' => AuthorName::MAX_LENGTH, 'autofocus' => true], $name->props['attributes']);
        PageComponents::one($form, 'Shared:FormButton', ['label' => 'cms.authors.modal.submit']);
        self::assertSame('cms.authors.title · CMS', $list->title);
    }

    public function testItKeepsTheSearchInItsBox(): void
    {
        $list = $this->list([AuthorMother::create(id: self::ID)], '  isaac ', 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_authors', 'name' => 'name', 'value' => 'isaac', 'label' => 'cms.authors.search.label']);
    }

    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, string $action, string $label): void
    {
        $list = $this->list([], $search, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        self::assertSame([[$action, $label]], array_map(static fn (PageComponent $child): array => [$child->name, $child->props['label']], $empty->children));
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no author at all' => ['', 'cms.authors.empty.title', 'cms.authors.empty.message', 'Shared:ModalTrigger', 'cms.authors.empty.action'];
        yield 'no author found' => [' ana ', 'cms.authors.search.empty.title', 'cms.authors.search.empty.message(%name%=ana)', 'Shared:ButtonLink', 'cms.authors.search.empty.action'];
    }

    public function testItListsEachAuthorLeadingToItsEditor(): void
    {
        $list = $this->list([AuthorMother::published(id: self::ID, publishedName: 'Antes')], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceList', ['label' => 'cms.authors.list.label']);
        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => AuthorMother::NAME,
            'url' => '/cms_authors_edit?id='.self::ID,
            'meta' => '/autor/isaac.html · cms.authors.list.updated_at(%date%=7 nov 2026)',
            'status' => 'cms.authors.status.changed.label',
        ]);
    }

    #[DataProvider('addresses')]
    public function testItListsEachAuthorAtItsAddress(Author $author, string $address): void
    {
        $list = $this->list([$author], '', 1, false);

        $meta = PageComponents::one($list, 'Shared:ResourceRow', [])->props['meta'];
        self::assertIsString($meta);
        self::assertStringStartsWith($address.' · ', $meta);
    }

    /**
     * @return iterable<string, array{Author, string}>
     */
    public static function addresses(): iterable
    {
        $published = AuthorMother::published(id: self::ID);
        $published->edit(AuthorMother::NAME, 'isaac-abreu', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');

        yield 'a published author, at its address on the site' => [$published, '/autor/isaac.html'];
        yield 'a draft' => [AuthorMother::create(id: self::ID, slug: 'ana'), '/autor/ana.html'];
        yield 'a draft without a slug' => [AuthorMother::create(id: self::ID, slug: ''), 'cms.authors.list.no_slug'];
    }

    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheSearch(string $search, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list([AuthorMother::create(id: self::ID)], $search, $number, $hasMore);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', 1, false, null, null];
        yield 'one in between' => ['', 2, true, '/cms_authors', '/cms_authors?page=3'];
        yield 'one of a search' => [' ana ', 2, true, '/cms_authors?name=ana', '/cms_authors?name=ana&page=3'];
    }

    /**
     * @param list<Author> $authors
     */
    private function list(array $authors, string $search, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new AuthorList($urlGenerator, $translator, new AuthorState($translator), new ListPagination($translator, $urlGenerator))(
            new AuthorsResponse(array_map(AuthorResponse::fromAuthor(...), $authors), $hasMore),
            $search,
            $number,
        );
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
