<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Infrastructure\Controller\AuthorState;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageState;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PageRole;
use Cms\Page\Infrastructure\Controller\PageState;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Infrastructure\Controller\PostState;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Infrastructure\Controller\IncomingRedirectsPanel;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use Cms\Redirect\Infrastructure\Controller\RedirectRows;
use Cms\Relation\Application\UserResponse;
use Cms\Relation\Application\UsersResponse;
use Cms\Relation\Domain\ResourceType;
use Cms\Relation\Infrastructure\Controller\AuthorUserRows;
use Cms\Relation\Infrastructure\Controller\ImageUserRows;
use Cms\Relation\Infrastructure\Controller\PageUserRows;
use Cms\Relation\Infrastructure\Controller\PostUserRows;
use Cms\Relation\Infrastructure\Controller\RelationList;
use Cms\Relation\Infrastructure\Controller\SettingsUserRows;
use Cms\Relation\Infrastructure\Controller\UserRows;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\SettingsState;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the page of uses makes: of which resource it is, where it
 * leads back to, what it says when nothing uses it, and how it goes on
 * through what does. Routes are generated as their names; the texts are
 * their translation keys, with what their placeholders are given.
 */
final class RelationListTest extends TestCase
{
    private const string ID = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';
    private const string USER = '01a19e72-b07f-7d78-889c-fa4a14a4fb01';

    /**
     * The page tells of the resource it is of, and leads back to its editor.
     */
    #[DataProvider('resources')]
    public function testItLeadsBackToTheEditorOfTheResource(ResourceType $type, string $texts, string $editor): void
    {
        $list = $this->list($type, [], 1, false);

        $title = PageComponents::one($list, 'Shared:PageTitle', ['subtitle' => $texts.'.subtitle(%name%=Sobre mí)']);
        PageComponents::one($title, 'Shared:ButtonLink', ['url' => \sprintf('/%s?id=%s', $editor, self::ID), 'label' => $texts.'.back']);
    }

    /**
     * @return iterable<string, array{ResourceType, string, string}>
     */
    public static function resources(): iterable
    {
        yield 'a page' => [ResourceType::Page, 'cms.relations.page', 'cms_pages_edit'];
        yield 'an image' => [ResourceType::Image, 'cms.relations.image', 'cms_images_edit'];
    }

    /**
     * @return iterable<string, array{ResourceType, string}>
     */
    public static function texts(): iterable
    {
        yield 'a page' => [ResourceType::Page, 'cms.relations.page'];
        yield 'an image' => [ResourceType::Image, 'cms.relations.image'];
    }

    /**
     * @return iterable<string, array{ResourceType}>
     */
    public static function types(): iterable
    {
        yield 'a page' => [ResourceType::Page];
        yield 'an image' => [ResourceType::Image];
    }

    /**
     * With nothing using the resource, the page says so; otherwise it lists
     * what does, and goes on through the pages of it.
     */
    #[DataProvider('texts')]
    public function testItTellsWhenNothingUsesTheResource(ResourceType $type, string $texts): void
    {
        $list = $this->list($type, [], 1, false);

        PageComponents::one($list, 'Shared:EmptyState', ['title' => $texts.'.empty.title', 'message' => $texts.'.empty.message']);
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', ['label' => $texts.'.list(%name%=Sobre mí)']));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    #[DataProvider('pagination')]
    public function testItListsWhatUsesTheResourceThroughItsPages(ResourceType $type, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list($type, [new UserResponse('page', self::USER)], $number, $hasMore);

        $rows = PageComponents::one($list, 'Shared:ResourceList', ['label' => \sprintf('cms.relations.%s.list(%%name%%=Sobre mí)', $type->value)]);
        PageComponents::one($rows, 'Shared:ResourceRow', ['url' => '/cms_pages_edit?id='.self::USER]);
        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
        self::assertSame([], PageComponents::find($list, 'Shared:EmptyState', []));
    }

    /**
     * @return iterable<string, array{ResourceType, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page of uses of a page' => [ResourceType::Page, 1, false, null, null];
        yield 'one of many of an image' => [
            ResourceType::Image,
            2,
            true,
            '/cms_relations?type=images&id='.self::ID,
            '/cms_relations?type=images&id='.self::ID.'&page=3',
        ];
        yield 'the first of many of a page' => [ResourceType::Page, 1, true, null, '/cms_relations?type=pages&id='.self::ID.'&page=2'];
    }

    /**
     * The redirects that lead to the resource are told last, as they keep it
     * on the site.
     */
    #[DataProvider('types')]
    public function testItTellsOfTheRedirectsLeadingToTheResourceLast(ResourceType $type): void
    {
        $list = $this->list($type, [], 1, false);

        $last = $list->main[\count($list->main) - 1];
        self::assertSame(['Shared:Panel', 'cms.redirects.incoming.title'], [$last->name, $last->props['title']]);
        self::assertSame(\sprintf('cms.redirects.incoming.%s.empty(%%name%%=Sobre mí)', $type->value), $last->props['description']);
    }

    /**
     * @param list<UserResponse> $users
     */
    private function list(ResourceType $type, array $users, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([PageMother::create(id: self::USER, title: 'Contacto', slug: 'contacto')]);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $redirectRepository = self::createStub(RedirectRepository::class);
        $redirectRepository->method('matching')->willReturn([]);

        $list = new RelationList(
            $urlGenerator,
            $translator,
            new UserRows(
                new PageUserRows($translator, $urlGenerator, $pageRepository, new PageState($translator), new PageRole($translator)),
                new ImageUserRows($translator, $urlGenerator, $imageRepository, new ImageState($translator)),
                new SettingsUserRows($translator, $urlGenerator, self::createStub(SettingsRepository::class), new SettingsState($translator)),
                new AuthorUserRows($translator, $urlGenerator, self::createStub(AuthorRepository::class), new AuthorState($translator)),
                new PostUserRows($translator, $urlGenerator, self::createStub(PostRepository::class), new PostState($translator)),
            ),
            new ListPagination($translator, $urlGenerator),
            new IncomingRedirectsPanel(
                $redirectRepository,
                new RedirectRows(new RedirectNames($pageRepository, $imageRepository, $translator), $urlGenerator, $translator),
                $translator,
                $urlGenerator,
            ),
        )($type, self::ID, 'Sobre mí', new UsersResponse($users, $hasMore), $number);
        // Whatever a test asks, the page gives every component what it takes.
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
