<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesResponse;
use Cms\Page\Domain\Page as PageAggregate;
use Cms\Page\Domain\PageType;
use Cms\Page\Infrastructure\Controller\PageList;
use Cms\Page\Infrastructure\Controller\PageRole;
use Cms\Page\Infrastructure\Controller\PageState;
use Cms\Tests\Page\Domain\PageMother;
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
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The decisions the list of pages makes: what it lists, what it says when
 * there is nothing to list, and where each page and each search leads.
 * Routes are generated as their names; the texts are their translation keys,
 * with what their placeholders are given.
 */
final class PageListTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string ID = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    #[\Override]
    protected function setUp(): void
    {
        // Editing a page dates it.
        self::mockTime(ClockMother::now());
    }

    /**
     * A page is created from its title alone, which the creation reads.
     */
    public function testItCreatesAPageFromItsTitle(): void
    {
        $list = $this->list([], '', 1, false);

        $form = PageComponents::one($list, 'Shared:Form', ['action' => '/cms_pages_create']);
        $title = PageComponents::one($form, 'Shared:FormInput', ['name' => 'title']);
        self::assertSame(['required' => true, 'autofocus' => true], $title->props['attributes']);
        self::assertCount(1, PageComponents::find($form, 'Shared:FormButton', []));
    }

    /**
     * The search box keeps what was searched, as the search took it.
     */
    public function testItKeepsTheSearchInItsBox(): void
    {
        $list = $this->list([PageMother::create(id: self::ID)], '  guía ', 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_pages', 'name' => 'title', 'value' => 'guía']);
    }

    /**
     * With no page at all, the list invites to create the first one; with
     * none found, it tells what was searched and leads back to every page.
     */
    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, string $action): void
    {
        $list = $this->list([], $search, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        self::assertSame([$action], array_map(static fn (PageComponent $child): string => $child->name, $empty->children));
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no page at all' => ['', 'cms.pages.empty.title', 'cms.pages.empty.message', 'Shared:ModalTrigger'];
        yield 'no page found' => [' guía ', 'cms.pages.search.empty.title', 'cms.pages.search.empty.message(%title%=guía)', 'Shared:ButtonLink'];
    }

    public function testItOpensTheModalToCreateTheFirstPage(): void
    {
        $list = $this->list([], '', 1, false);

        $modal = PageComponents::one($list, 'Shared:Modal', []);
        PageComponents::one($list, 'Shared:ModalTrigger', ['target' => $modal->props['id']]);
    }

    public function testItLeadsBackToEveryPageFromASearchWithoutResults(): void
    {
        $list = $this->list([], 'guía', 1, false);

        PageComponents::one($list, 'Shared:ButtonLink', ['url' => '/cms_pages']);
    }

    /**
     * Each page opens its editor, and tells where the site serves it, when it
     * last changed, where it stands and the role it fills.
     */
    public function testItListsEachPageLeadingToItsEditor(): void
    {
        $list = $this->list([
            PageMother::create(id: self::ID, title: 'Contacto', slug: 'contacto', type: PageType::Contact),
        ], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => 'Contacto',
            'url' => '/cms_pages_edit?id='.self::ID,
            'meta' => '/contacto.html · cms.pages.list.updated_at(%date%=7 nov 2026)',
            'badge' => 'cms.pages.types.contact',
            'status' => 'cms.pages.status.draft.label',
        ]);
    }

    /**
     * A page is listed at the address the site serves it at: the home page
     * at the root, a published one at its slug there, whatever it is edited
     * with, any other at the one saved, or as having none yet.
     */
    #[DataProvider('addresses')]
    public function testItListsEachPageAtItsAddress(PageAggregate $page, string $address): void
    {
        $list = $this->list([$page], '', 1, false);

        self::assertStringStartsWith($address.' · ', $this->meta($list));
    }

    /**
     * @return iterable<string, array{PageAggregate, string}>
     */
    public static function addresses(): iterable
    {
        yield 'the home page' => [PageMother::home(id: self::ID), '/'];
        yield 'a published page' => [PageMother::published(id: self::ID, slug: 'sobre-mi'), '/sobre-mi.html'];
        yield 'a draft' => [PageMother::create(id: self::ID, slug: 'borrador'), '/borrador.html'];
        yield 'a draft without a slug' => [PageMother::create(id: self::ID, slug: ''), 'cms.pages.list.no_slug'];
    }

    /**
     * The site keeps serving a published page at its slug there until it is
     * published again with the one it is edited with.
     */
    public function testItListsAPublishedPageAtItsAddressOnTheSite(): void
    {
        $page = PageMother::published(id: self::ID, slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');

        $list = $this->list([$page], '', 1, false);

        self::assertStringStartsWith('/sobre-mi.html · ', $this->meta($list));
    }

    /**
     * The list goes on through the pages of its search, and the first one
     * goes without a number.
     */
    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheSearch(string $search, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list([PageMother::create(id: self::ID)], $search, $number, $hasMore);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', 1, false, null, null];
        yield 'the first of many' => ['', 1, true, null, '/cms_pages?page=2'];
        yield 'one in between' => ['', 2, true, '/cms_pages', '/cms_pages?page=3'];
        yield 'one of a search' => [' guía ', 2, true, '/cms_pages?title=gu%C3%ADa', '/cms_pages?title=gu%C3%ADa&page=3'];
    }

    /**
     * What the only row of the list tells of its page.
     */
    private function meta(Page $list): string
    {
        $meta = PageComponents::one($list, 'Shared:ResourceRow', [])->props['meta'];
        self::assertIsString($meta);

        return $meta;
    }

    /**
     * @param list<PageAggregate> $pages
     */
    private function list(array $pages, string $search, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new PageList(
            $urlGenerator,
            $translator,
            new PageRole($translator),
            new PageState($translator),
            new ListPagination($translator, $urlGenerator),
        )(new PagesResponse(array_map(PageResponse::fromPage(...), $pages), $hasMore), $search, $number);
        // Whatever a test asks, the list gives every component what it takes.
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
