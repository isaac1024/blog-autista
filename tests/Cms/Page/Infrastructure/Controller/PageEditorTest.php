<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\Robots;
use Cms\Page\Domain\Seo;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Cms\Page\Infrastructure\Controller\PageEditor;
use Cms\Page\Infrastructure\Controller\PageRole;
use Cms\Page\Infrastructure\Controller\PageState;
use Cms\Page\Infrastructure\Controller\SharedPreviewLink;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Infrastructure\Controller\LastAddressPanel;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use Cms\Redirect\Infrastructure\Controller\ReplacedRedirectNotice;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Ui\Page\Page as ComposedPage;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The decisions the editor of a page makes: what it offers, and where it
 * leads. Routes are generated as their names, so the tests tell which one
 * each action reaches; the texts are their translation keys, which the
 * catalogues of the site must define.
 */
final class PageEditorTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string ID = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    private const string SITE = RouteNameUrlGenerator::SITE;

    private const string PREVIEW = 'https://cms.blogautista.localhost/pages/preview?signature=firmada';

    #[\Override]
    protected function setUp(): void
    {
        // Editing a page dates it.
        self::mockTime(ClockMother::now());
    }

    /**
     * What is saved can be seen, and sent, through the signed link.
     */
    public function testItOffersThePreviewThroughTheSharedLink(): void
    {
        $editor = $this->edit(PageMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:CopyField', ['value' => self::PREVIEW]);
        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => self::PREVIEW]);
    }

    /**
     * The site serves the page at the slug it was published with, not at
     * the one edited since.
     */
    public function testItLeadsToAPublishedPageWhereTheSiteServesIt(): void
    {
        $page = PageMother::published(id: self::ID, slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');

        $editor = $this->edit($page);

        self::assertSame(self::SITE.'/sobre-mi.html', $this->viewOnSite($editor));
    }

    public function testItLeadsToThePublishedHomePageAtTheRootOfTheSite(): void
    {
        $editor = $this->edit(PageMother::published(id: self::ID, type: PageType::Home));

        self::assertSame(self::SITE.'/', $this->viewOnSite($editor));
    }

    #[DataProvider('pagesOffTheSite')]
    public function testItLeadsNowhereOnTheSiteForAPageOffIt(Page $page): void
    {
        $editor = $this->edit($page);

        self::assertNull($this->viewOnSite($editor));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesOffTheSite(): iterable
    {
        yield 'a draft' => [PageMother::create(id: self::ID)];
        yield 'an unpublished page' => [PageMother::unpublished(id: self::ID)];
    }

    /**
     * Whatever it stands, what uses the page is a link away.
     */
    public function testItLeadsToWhatUsesThePage(): void
    {
        $editor = $this->edit(PageMother::published(id: self::ID));

        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => '/cms_relations?type=pages&id='.self::ID]);
    }

    /**
     * Each action on the page is offered only when the page allows it.
     *
     * @param array{discard: bool, unpublish: bool, delete: bool} $offered
     */
    #[DataProvider('actionsByState')]
    public function testItOffersOnlyTheActionsThePageAllows(Page $page, array $offered): void
    {
        $editor = $this->edit($page);

        self::assertSame($offered, [
            'discard' => $this->confirms($editor, 'cms_pages_discard'),
            'unpublish' => [] !== PageComponents::find($editor, 'Shared:ButtonLink', ['url' => '/cms_pages_unpublish_form?id='.self::ID]),
            'delete' => $this->confirms($editor, 'cms_pages_delete'),
        ]);
    }

    /**
     * @return iterable<string, array{Page, array{discard: bool, unpublish: bool, delete: bool}}>
     */
    public static function actionsByState(): iterable
    {
        yield 'a draft' => [
            PageMother::create(id: self::ID),
            ['discard' => false, 'unpublish' => false, 'delete' => true],
        ];
        yield 'a published page as it is on the site' => [
            PageMother::published(id: self::ID),
            ['discard' => false, 'unpublish' => true, 'delete' => false],
        ];
        yield 'a published page with changes' => [
            PageMother::published(id: self::ID, title: 'Quién soy', publishedTitle: 'Sobre mí'),
            ['discard' => true, 'unpublish' => true, 'delete' => false],
        ];
        yield 'an unpublished page edited since' => [
            PageMother::unpublished(id: self::ID, title: 'Quién soy', publishedTitle: 'Sobre mí'),
            ['discard' => false, 'unpublish' => false, 'delete' => true],
        ];
        yield 'a unique page as a draft' => [
            PageMother::create(id: self::ID, title: 'Contacto', slug: 'contacto', type: PageType::Contact),
            ['discard' => false, 'unpublish' => false, 'delete' => false],
        ];
        yield 'the published home page with changes' => [
            PageMother::published(id: self::ID, type: PageType::Home, title: 'Inicio', publishedTitle: 'Portada'),
            ['discard' => true, 'unpublish' => false, 'delete' => false],
        ];
    }

    /**
     * Publishing is offered while it would change the site, worded for
     * whether the page is there already, and saving is the main action
     * otherwise.
     */
    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Page $page, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit($page);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'page-publish-form', 'waitsFor' => 'page-form']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn ($button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.pages.edit.submit', 'variant' => $saveVariant]);
        // It publishes what is saved, so it is a form of its own, out of sight.
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_pages_publish?id='.self::ID, 'id' => 'page-publish-form', 'hidden' => true]);
    }

    /**
     * @return iterable<string, array{Page, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [PageMother::create(id: self::ID), 'cms.pages.edit.publish', 'secondary'];
        yield 'a published page as it is on the site' => [PageMother::published(id: self::ID), null, 'primary'];
        yield 'a published page with changes' => [
            PageMother::published(id: self::ID, title: 'Quién soy', publishedTitle: 'Sobre mí'),
            'cms.pages.edit.republish',
            'secondary',
        ];
        yield 'an unpublished page' => [PageMother::unpublished(id: self::ID), 'cms.pages.edit.publish', 'secondary'];
    }

    /**
     * A page is edited at the address it is saved with, which search results
     * preview as it is typed.
     */
    public function testItEditsTheAddressOfAPage(): void
    {
        $page = PageMother::published(id: self::ID, slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');

        $editor = $this->edit($page);

        PageComponents::one($editor, 'Shared:FormInput', ['name' => 'slug', 'value' => 'quien-soy']);
        PageComponents::one($editor, 'Shared:SearchPreview', [
            'url' => self::SITE.'/quien-soy.html',
            'sources' => ['title' => 'page-title', 'description' => 'page-meta-description', 'slug' => 'page-slug'],
        ]);
    }

    /**
     * The home page is served at the root of the site, so it has no address
     * to edit, and search results preview it there.
     */
    public function testItEditsNoAddressOfTheHomePage(): void
    {
        $editor = $this->edit(PageMother::home(id: self::ID));

        self::assertSame([], PageComponents::find($editor, 'Shared:FormInput', ['name' => 'slug']));
        PageComponents::one($editor, 'Shared:FormHint', ['id' => 'page-home-address']);
        PageComponents::one($editor, 'Shared:SearchPreview', [
            'url' => self::SITE.'/',
            'sources' => ['title' => 'page-title', 'description' => 'page-meta-description'],
        ]);
    }

    /**
     * Every choice search engines can be given is offered, with the one the
     * page gives them chosen.
     */
    public function testItOffersEveryRobotsChoiceWithThePagesChosen(): void
    {
        $editor = $this->edit(PageMother::create(id: self::ID, robots: Robots::NoindexFollow));

        PageComponents::one($editor, 'Shared:FormRadioGroup', [
            'name' => 'robots',
            'value' => 'noindex,follow',
            // Each explained in words of its own.
            'options' => array_map(static fn (Robots $robots): array => [
                'value' => $robots->value,
                'label' => \sprintf('cms.pages.edit.seo.robots.%s.label', $robots->value),
                'hint' => \sprintf('cms.pages.edit.seo.robots.%s.hint', $robots->value),
            ], Robots::cases()),
        ]);
    }

    /**
     * The editor saves the page with the fields its update reads, held to
     * what the page accepts, so the browser refuses what the page would.
     */
    public function testItSavesThePageWithTheFieldsItsUpdateReads(): void
    {
        $editor = $this->edit(PageMother::create(id: self::ID));

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_pages_update?id='.self::ID, 'id' => 'page-form']);
        $title = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'title', 'singleLine' => true]);
        $slug = PageComponents::one($form, 'Shared:FormInput', ['name' => 'slug']);
        PageComponents::one($form, 'Shared:RichTextEditor', ['name' => 'content']);
        $metaDescription = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'meta_description']);
        PageComponents::one($form, 'Shared:FormRadioGroup', ['name' => 'robots']);
        self::assertSame(
            ['required' => true, 'minlength' => Title::MIN_LENGTH, 'maxlength' => Title::MAX_LENGTH],
            $this->constraints($title, ['required', 'minlength', 'maxlength']),
        );
        self::assertSame(
            ['minlength' => Slug::MIN_LENGTH, 'maxlength' => Slug::MAX_LENGTH, 'pattern' => Slug::SHAPE],
            $this->constraints($slug, ['minlength', 'maxlength', 'pattern']),
        );
        self::assertSame(['maxlength' => Seo::DESCRIPTION_MAX_LENGTH], $this->constraints($metaDescription, ['maxlength']));
    }

    /**
     * Where the page stands is told by its badge and by the bar of its
     * actions; changes not on the site yet are what matters most.
     */
    #[DataProvider('pagesWhereverTheyStand')]
    public function testItTellsWhereThePageStands(Page $page, string $state): void
    {
        $editor = $this->edit($page);

        PageComponents::one($editor, 'Shared:PageTitle', ['status' => \sprintf('cms.pages.status.%s.label', $state)]);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => \sprintf('cms.pages.status.%s.description', $state)]);
    }

    /**
     * @return iterable<string, array{Page, string}>
     */
    public static function pagesWhereverTheyStand(): iterable
    {
        yield 'a draft' => [PageMother::create(id: self::ID), 'draft'];
        yield 'a published page as it is on the site' => [PageMother::published(id: self::ID), 'published'];
        yield 'a published page with changes' => [PageMother::published(id: self::ID, title: 'Quién soy', publishedTitle: 'Sobre mí'), 'changed'];
        yield 'an unpublished page' => [PageMother::unpublished(id: self::ID), 'unpublished'];
    }

    /**
     * A page doesn't link itself, so the search for pages to link leaves it out.
     */
    public function testItLeavesThePageOutOfThePagesItCanLink(): void
    {
        $editor = $this->edit(PageMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:RichTextEditor', ['pageSearchUrl' => '/cms_pages_search?except='.self::ID]);
    }

    /**
     * A unique page is told apart by the role it fills; a generic one has none.
     */
    public function testItNamesTheRoleOfAUniquePage(): void
    {
        $unique = $this->edit(PageMother::create(id: self::ID, title: 'Escríbeme', slug: 'contacto', type: PageType::Contact));
        $generic = $this->edit(PageMother::create(id: self::ID));

        PageComponents::one($unique, 'Shared:PageTitle', ['badge' => 'cms.pages.types.contact']);
        PageComponents::one($generic, 'Shared:PageTitle', ['badge' => null]);
    }

    /**
     * Whether the editor offers the action, confirmed first, and submitted
     * with a button of its own.
     */
    private function confirms(ComposedPage $editor, string $route): bool
    {
        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => \sprintf('/%s?id=%s', $route, self::ID)]);

        foreach ($forms as $form) {
            self::assertSame(['Shared:FormButton'], array_map(static fn (PageComponent $child): string => $child->name, $form->children));
        }

        return [] !== $forms;
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    private function constraints(PageComponent $field, array $names): array
    {
        $attributes = $field->props['attributes'] ?? [];
        self::assertIsArray($attributes);

        return array_intersect_key($attributes, array_flip($names));
    }

    private function viewOnSite(ComposedPage $editor): ?string
    {
        $links = PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.pages.edit.view_on_site']);
        self::assertLessThanOrEqual(1, \count($links));

        if ([] === $links) {
            return null;
        }

        $url = $links[0]->props['url'];
        self::assertIsString($url);

        return $url;
    }

    private function edit(Page $page): ComposedPage
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('isOnSite')->willReturn(true);
        $redirectNames = new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), $translator);

        $editor = new PageEditor(
            $urlGenerator,
            $translator,
            new TiptapRichText(),
            new PageRole($translator),
            new PageState($translator),
            new LastAddressPanel(
                self::createStub(RedirectRepository::class),
                $siteResources,
                $redirectNames,
                $translator,
                $urlGenerator,
                new ReplacedRedirectNotice($redirectNames, $translator),
            ),
        );

        $composed = $editor(PageResponse::fromPage($page), new SharedPreviewLink(self::PREVIEW, ClockMother::now()));
        // Whatever a test asks, the editor gives every component what it takes.
        ComponentContracts::assertHonoured($composed);

        return $composed;
    }
}
