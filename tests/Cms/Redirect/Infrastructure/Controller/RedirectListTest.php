<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Image\Domain\Image;
use Cms\Page\Domain\Page as PageAggregate;
use Cms\Post\Domain\Post;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Application\RedirectsResponse;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\RedirectList;
use Cms\Redirect\Infrastructure\Controller\RedirectRows;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the list of redirects makes: what it lists, how it names
 * where each leads, what it says when there is nothing to list, and where
 * each redirect and each page of results leads. Routes are generated as
 * their names; the texts are their translation keys, with what their
 * placeholders are given.
 */
final class RedirectListTest extends TestCase
{
    use NamesResources;

    private const string ID = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string DELETED = '0196f3a0-0000-7000-8000-0000000000a9';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string POST = '0196f3a0-0000-7000-8000-0000000000f1';
    private const string SECTION = '0196f3a0-0000-7000-8000-0000000000f2';
    private const string TAG = '0196f3a0-0000-7000-8000-0000000000f3';
    private const string AUTHOR = '0196f3a0-0000-7000-8000-0000000000f4';

    public function testItLeadsToCreateARedirect(): void
    {
        $title = PageComponents::one($this->list([$this->redirect()], '', null, 1, false), 'Shared:PageTitle', []);

        PageComponents::one($title, 'Shared:ButtonLink', ['url' => '/cms_redirects_new']);
    }

    /**
     * The search box keeps what was searched, as the search took it; a list
     * narrowed to a resource searches nothing.
     */
    #[DataProvider('searches')]
    public function testItKeepsTheSearchInItsBox(string $search, ?Resource $narrowedTo, string $kept): void
    {
        $list = $this->list([$this->redirect()], $search, $narrowedTo, 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_redirects', 'name' => 'source', 'value' => $kept]);
    }

    /**
     * @return iterable<string, array{string, ?resource, string}>
     */
    public static function searches(): iterable
    {
        yield 'a search' => [' vieja ', null, 'vieja'];
        yield 'a list narrowed to a page' => [' vieja ', Resource::page(self::PAGE), ''];
    }

    /**
     * With no redirect at all, the list invites to create the first one;
     * with none found, it tells what was searched and leads back to every
     * redirect.
     */
    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, string $url): void
    {
        $list = $this->list([], $search, null, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        PageComponents::one($empty, 'Shared:ButtonLink', ['url' => $url]);
        self::assertCount(1, $empty->children);
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no redirect at all' => ['', 'cms.redirects.empty.title', 'cms.redirects.empty.message', '/cms_redirects_new'];
        yield 'no redirect found' => [' vieja ', 'cms.redirects.search.empty.title', 'cms.redirects.search.empty.message(%source%=vieja)', '/cms_redirects'];
    }

    /**
     * Each redirect opens where it is edited, named by its address, with
     * where it leads, by the title of a page or the alternative text of an
     * image, as deleted when it is no more, or as gone.
     */
    #[DataProvider('targets')]
    public function testItListsEachRedirectWithWhereItLeads(?string $type, ?string $id, string $leads, string $status): void
    {
        $list = $this->list(
            [$this->redirect($type, $id)],
            '',
            null,
            1,
            false,
            [PageMother::published(id: self::PAGE, title: 'Sobre mí')],
            [ImageMother::published(id: self::IMAGE, alt: 'Un faro')],
            [PostMother::published(id: self::POST)],
            [TermMother::published(id: self::SECTION), TermMother::published(id: self::TAG, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno')],
            [AuthorMother::published(id: self::AUTHOR)],
        );

        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => '/vieja.html',
            'url' => '/cms_redirects_edit?id='.self::ID,
            'meta' => $leads,
            'status' => $status,
        ]);
    }

    /**
     * @return iterable<string, array{?string, ?string, string, string}>
     */
    public static function targets(): iterable
    {
        yield 'to a page' => ['page', self::PAGE, 'cms.redirects.target.page(%name%=Sobre mí)', 'cms.redirects.status.moved'];
        yield 'to an image' => ['image', self::IMAGE, 'cms.redirects.target.image(%name%=Un faro)', 'cms.redirects.status.moved'];
        yield 'to a page deleted since' => ['page', self::DELETED, 'cms.redirects.target.deleted', 'cms.redirects.status.moved'];
        yield 'to a post' => ['post', self::POST, 'cms.redirects.target.post(%name%='.PostMother::TITLE.')', 'cms.redirects.status.moved'];
        yield 'to a section' => ['term', self::SECTION, 'cms.redirects.target.section(%name%='.TermMother::NAME.')', 'cms.redirects.status.moved'];
        yield 'to a tag' => ['term', self::TAG, 'cms.redirects.target.tag(%name%=Sueño)', 'cms.redirects.status.moved'];
        yield 'to an author' => ['author', self::AUTHOR, 'cms.redirects.target.author(%name%='.AuthorMother::NAME.')', 'cms.redirects.status.moved'];
        yield 'to a term deleted since' => ['term', self::DELETED, 'cms.redirects.target.deleted', 'cms.redirects.status.moved'];
        yield 'nowhere' => [null, null, 'cms.redirects.target.gone', 'cms.redirects.status.gone'];
    }

    /**
     * Every redirect of the list is named after its own target, however
     * many resources they lead to.
     */
    public function testItNamesWhereEachRedirectLeads(): void
    {
        $list = $this->list(
            [$this->redirect('page', self::PAGE), new RedirectResponse(self::OTHER, '/otra.html', false, 'image', self::IMAGE, null, null, ClockMother::now(), ClockMother::now())],
            '',
            null,
            1,
            false,
            [PageMother::published(id: self::PAGE, title: 'Sobre mí')],
            [ImageMother::published(id: self::IMAGE, alt: 'Un faro')],
        );

        self::assertSame(
            ['cms.redirects.target.page(%name%=Sobre mí)', 'cms.redirects.target.image(%name%=Un faro)'],
            array_map(static fn (PageComponent $row): mixed => $row->props['meta'], PageComponents::find($list, 'Shared:ResourceRow', [])),
        );
    }

    /**
     * The list goes on through the pages of its search, or of the resource
     * it is narrowed to, and the first one goes without a number.
     */
    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheList(string $search, ?Resource $narrowedTo, int $number, ?string $previous, ?string $next): void
    {
        $list = $this->list([$this->redirect()], $search, $narrowedTo, $number, true);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, ?resource, int, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'every redirect' => ['', null, 1, null, '/cms_redirects?page=2'];
        yield 'a search' => [' vieja ', null, 2, '/cms_redirects?source=vieja', '/cms_redirects?source=vieja&page=3'];
        yield 'those leading to a page' => [' vieja ', Resource::page(self::PAGE), 2, '/cms_redirects?target=page%3A'.self::PAGE, '/cms_redirects?target=page%3A'.self::PAGE.'&page=3'];
    }

    private function redirect(?string $type = 'page', ?string $id = self::PAGE): RedirectResponse
    {
        return new RedirectResponse(self::ID, '/vieja.html', false, $type, $id, null, null, ClockMother::now(), ClockMother::now());
    }

    /**
     * @param list<RedirectResponse> $redirects
     * @param list<PageAggregate>    $pages
     * @param list<Image>            $images
     * @param list<Post>             $posts
     * @param list<Term>             $terms
     * @param list<Author>           $authors
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function list(array $redirects, string $search, ?Resource $narrowedTo, int $number, bool $hasMore, array $pages = [], array $images = [], array $posts = [], array $terms = [], array $authors = []): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();

        $list = new RedirectList(
            $urlGenerator,
            $translator,
            new RedirectRows($this->redirectNames($translator, $pages, $images, $posts, $terms, $authors), $urlGenerator, $translator),
            new ListPagination($translator, $urlGenerator),
        )(new RedirectsResponse($redirects, $hasMore), $search, $narrowedTo, $number);
        // Whatever a test asks, the list gives every component what it takes.
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
