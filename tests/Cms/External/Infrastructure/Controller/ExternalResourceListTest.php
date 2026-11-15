<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Application\ExternalResourcesResponse;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Infrastructure\Controller\ExternalResourceList;
use Cms\External\Infrastructure\Controller\ExternalResourceState;
use Cms\Tests\External\Domain\ExternalResourceMother;
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
 * What the list of external resources is made of: how to add one, an alert
 * when some on the site are gone, how to narrow it, a row per resource and
 * what to do when it lists none.
 */
final class ExternalResourceListTest extends TestCase
{
    private const string ID = ExternalResourceMother::ID;

    public function testItNamesTheListAndAddsAResourceByItsAddress(): void
    {
        $list = $this->list([ExternalResourceMother::video()]);

        self::assertSame('cms.externals.title · CMS', $list->title);
        $title = PageComponents::one($list, 'Shared:PageTitle', ['title' => 'cms.externals.title', 'subtitle' => 'cms.externals.subtitle']);
        $modal = PageComponents::one($title, 'Shared:Modal', ['id' => 'new-external', 'trigger' => 'cms.externals.add', 'title' => 'cms.externals.modal.title', 'description' => 'cms.externals.modal.description']);
        PageComponents::one($modal, 'Shared:Form', ['action' => '/cms_externals_create', 'method' => 'post', 'csrfTokenId' => 'cms_externals_create']);
        PageComponents::one($modal, 'Shared:FormLabel', ['for' => 'external-new-url', 'text' => 'cms.externals.url_label']);
        PageComponents::one($modal, 'Shared:FormHint', ['id' => 'external-new-url-hint', 'text' => 'cms.externals.modal.url_hint']);
        PageComponents::one($modal, 'Shared:FormInput', ['name' => 'url', 'id' => 'external-new-url', 'type' => 'url', 'attributes' => ['required' => true, 'maxlength' => 2000, 'autofocus' => true, 'spellcheck' => 'false', 'aria-describedby' => 'external-new-url-hint']]);
        PageComponents::one($modal, 'Shared:FormButton', ['label' => 'cms.externals.modal.submit']);
    }

    /**
     * Each with what it is, its host, when it last changed and where it
     * stands, leading to its editor.
     */
    public function testItListsAResourcePerRow(): void
    {
        $list = $this->list([ExternalResourceMother::video(), ExternalResourceMother::article(id: '01a1e2a0-0000-7000-8000-000000000002', status: ResourceStatus::Published)]);

        PageComponents::one($list, 'Shared:ResourceList', ['label' => 'cms.externals.list.label']);
        self::assertSame([
            [
                'title' => 'Un vídeo',
                'url' => '/cms_externals_edit?id='.self::ID,
                'meta' => 'cms.externals.kind_by(%kind%=cms.externals.kind.video, %provider%=cms.externals.provider.youtube) · youtube.com · cms.externals.list.updated_at(%date%=7 nov 2026)',
                'status' => 'cms.externals.status.draft.label',
            ],
            [
                'title' => 'Una noticia',
                'url' => '/cms_externals_edit?id=01a1e2a0-0000-7000-8000-000000000002',
                'meta' => 'cms.externals.kind.article · example.org · cms.externals.list.updated_at(%date%=7 nov 2026)',
                'status' => 'cms.externals.status.published.label',
            ],
        ], array_map(static fn (PageComponent $row): array => $row->props, PageComponents::find($list, 'Shared:ResourceRow', [])));
    }

    /**
     * The alert counts them, and leads to them through the filter, which
     * keeps the search.
     */
    #[DataProvider('filters')]
    public function testItNarrowsTheListToTheResourcesGoneAndWarnsOfThem(string $search, bool $unavailableOnly, int $unavailable, int $alerts, string $all, string $gone, string $action): void
    {
        $list = $this->list([ExternalResourceMother::video()], search: $search, unavailableOnly: $unavailableOnly, unavailable: $unavailable);

        self::assertCount($alerts, PageComponents::find($list, 'Shared:Alert', ['title' => 'cms.externals.unavailable_alert.title', 'message' => \sprintf('cms.externals.unavailable_alert.message(%%count%%=%d)', $unavailable)]));
        PageComponents::one($list, 'Shared:SegmentedNav', ['label' => 'cms.externals.filter.label', 'links' => [
            ['label' => 'cms.externals.filter.all', 'url' => $all, 'current' => !$unavailableOnly],
            ['label' => \sprintf('cms.externals.filter.unavailable(%%count%%=%d)', $unavailable), 'url' => $gone, 'current' => $unavailableOnly],
        ]]);
        PageComponents::one($list, 'Shared:SearchForm', ['action' => $action, 'name' => 'title', 'id' => 'external-search', 'label' => 'cms.externals.search.label', 'submit' => 'cms.externals.search.submit', 'value' => trim($search)]);
    }

    /**
     * @return iterable<string, array{string, bool, int, int, string, string, string}>
     */
    public static function filters(): iterable
    {
        yield 'all, none gone' => ['', false, 0, 0, '/cms_externals', '/cms_externals?filter=unavailable', '/cms_externals'];
        yield 'all, some gone' => ['', false, 2, 1, '/cms_externals', '/cms_externals?filter=unavailable', '/cms_externals'];
        yield 'those gone, searched' => [' vídeo ', true, 2, 0, '/cms_externals?title=v%C3%ADdeo', '/cms_externals?filter=unavailable&title=v%C3%ADdeo', '/cms_externals?filter=unavailable'];
    }

    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheList(string $search, bool $unavailableOnly, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list([ExternalResourceMother::video()], $hasMore, $search, $unavailableOnly, $number);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, bool, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', false, 1, false, null, null];
        yield 'one in between' => ['', false, 2, true, '/cms_externals', '/cms_externals?page=3'];
        yield 'one of a search of those gone' => [' vídeo ', true, 2, true, '/cms_externals?title=v%C3%ADdeo&filter=unavailable', '/cms_externals?title=v%C3%ADdeo&filter=unavailable&page=3'];
    }

    /**
     * With none kept, it offers to add the first; with a search or the
     * filter leaving none, to see them all again.
     *
     * @param array<string, string> $props
     */
    #[DataProvider('emptyLists')]
    public function testItTellsWhatToDoWhenItListsNone(string $search, bool $unavailableOnly, string $title, string $message, string $action, array $props): void
    {
        $list = $this->list([], search: $search, unavailableOnly: $unavailableOnly);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        PageComponents::one($empty, $action, $props);
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{string, bool, string, string, string, array<string, string>}>
     */
    public static function emptyLists(): iterable
    {
        yield 'none kept' => ['', false, 'cms.externals.empty.title', 'cms.externals.empty.message', 'Shared:ModalTrigger', ['target' => 'new-external', 'label' => 'cms.externals.empty.action']];
        yield 'none found' => [' vídeo ', false, 'cms.externals.search.empty.title', 'cms.externals.search.empty.message(%title%=vídeo)', 'Shared:ButtonLink', ['url' => '/cms_externals', 'label' => 'cms.externals.search.empty.action']];
        yield 'none gone' => ['', true, 'cms.externals.search.empty.title', 'cms.externals.search.empty.unavailable', 'Shared:ButtonLink', ['url' => '/cms_externals', 'label' => 'cms.externals.search.empty.action']];
    }

    public function testItTellsOfAResourceOnTheSiteThatIsGone(): void
    {
        $list = $this->list([ExternalResourceMother::video(status: ResourceStatus::Published, availability: Availability::Unavailable)]);

        PageComponents::one($list, 'Shared:ResourceRow', ['status' => 'cms.externals.status.unavailable.label']);
    }

    /**
     * @param list<ExternalResource> $resources
     */
    private function list(array $resources, bool $hasMore = false, string $search = '', bool $unavailableOnly = false, int $number = 1, int $unavailable = 0): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new ExternalResourceList($urlGenerator, $translator, new ExternalResourceState($translator), new ListPagination($translator, $urlGenerator))(
            new ExternalResourcesResponse(array_map(ExternalResourceResponse::fromResource(...), $resources), $hasMore),
            $search,
            $unavailableOnly,
            $number,
            $unavailable,
        );
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
