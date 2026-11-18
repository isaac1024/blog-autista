<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostsResponse;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostTitle;
use Cms\Post\Infrastructure\Controller\PostList;
use Cms\Post\Infrastructure\Controller\PostUrl;
use Cms\Shared\Infrastructure\Ui\PostState;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

final class PostListTest extends TestCase
{
    public function testItCreatesAPostFromItsTitle(): void
    {
        $list = $this->list([], '', 1, false);

        PageComponents::one($list, 'Shared:PageTitle', ['title' => 'cms.posts.title', 'subtitle' => 'cms.posts.subtitle']);
        PageComponents::one($list, 'Shared:Modal', ['id' => 'new-post', 'trigger' => 'cms.posts.add', 'title' => 'cms.posts.modal.title', 'description' => 'cms.posts.modal.description']);
        $form = PageComponents::one($list, 'Shared:Form', ['action' => '/cms_posts_create', 'method' => 'post', 'csrfTokenId' => 'cms_posts_create']);
        PageComponents::one($form, 'Shared:FormLabel', ['for' => 'post-title', 'text' => 'cms.posts.title_label']);
        $title = PageComponents::one($form, 'Shared:FormInput', ['name' => 'title', 'id' => 'post-title']);
        self::assertSame(['required' => true, 'minlength' => PostTitle::MIN_LENGTH, 'maxlength' => PostTitle::MAX_LENGTH, 'autofocus' => true], $title->props['attributes']);
        PageComponents::one($form, 'Shared:FormButton', ['label' => 'cms.posts.modal.submit']);
        self::assertSame('cms.posts.title · CMS', $list->title);
    }

    public function testItKeepsTheSearchInItsBox(): void
    {
        $list = $this->list([PostMother::create()], '  ruido ', 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_posts', 'name' => 'title', 'id' => 'post-search', 'value' => 'ruido', 'label' => 'cms.posts.search.label', 'submit' => 'cms.posts.search.submit']);
    }

    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, string $action, string $label, string $target): void
    {
        $list = $this->list([], $search, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        self::assertSame([[$action, $label, $target]], array_map(static fn (PageComponent $child): array => [$child->name, $child->props['label'], $child->props['target'] ?? $child->props['url']], $empty->children));
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, string}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no post at all' => ['', 'cms.posts.empty.title', 'cms.posts.empty.message', 'Shared:ModalTrigger', 'cms.posts.empty.action', 'new-post'];
        yield 'no post found' => [' ruido ', 'cms.posts.search.empty.title', 'cms.posts.search.empty.message(%title%=ruido)', 'Shared:ButtonLink', 'cms.posts.search.empty.action', '/cms_posts'];
    }

    #[DataProvider('rows')]
    public function testItListsEachPostAtItsAddressOnTheSiteLeadingToItsEditor(Post $post, string $address, string $status): void
    {
        $list = $this->list([$post], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceList', ['label' => 'cms.posts.list.label']);
        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => PostMother::TITLE,
            'url' => '/cms_posts_edit?id='.PostMother::create()->id()->value(),
            'meta' => $address.' · cms.posts.list.updated_at(%date%=7 nov 2026)',
            'status' => $status,
        ]);
    }

    /**
     * @return iterable<string, array{Post, string, string}>
     */
    public static function rows(): iterable
    {
        yield 'a published post' => [PostMother::published(publishedTitle: 'Antes'), '/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html', 'cms.posts.status.changed.label'];
        yield 'a draft, without an address yet' => [PostMother::publishable(), 'cms.posts.list.off_site', 'cms.posts.status.draft.label'];
    }

    /**
     * Saved late on the 7th by the clock of the database, which is already
     * the 8th in Madrid, where the blog is edited.
     */
    public function testItDatesEachPostByTheDayOfMadrid(): void
    {
        $list = $this->list([PostMother::create(updatedAt: '2026-11-07 23:30:00')], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceRow', ['meta' => 'cms.posts.list.off_site · cms.posts.list.updated_at(%date%=8 nov 2026)']);
    }

    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheSearch(string $search, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        PageComponents::one($this->list([PostMother::create()], $search, $number, $hasMore), 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', 1, false, null, null];
        yield 'one in between' => ['', 2, true, '/cms_posts', '/cms_posts?page=3'];
        yield 'one of a search' => [' ruido ', 2, true, '/cms_posts?title=ruido', '/cms_posts?title=ruido&page=3'];
    }

    /**
     * @param list<Post> $posts
     */
    private function list(array $posts, string $search, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new PostList($urlGenerator, $translator, new PostState($translator), new PostUrl($urlGenerator), new ListPagination($translator, $urlGenerator))(
            new PostsResponse(array_map(PostResponse::fromPost(...), $posts), $hasMore),
            $search,
            $number,
        );
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
