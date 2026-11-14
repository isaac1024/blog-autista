<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Infrastructure\Controller\AuthorState;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\Title;
use Cms\Post\Infrastructure\Controller\PostEditor;
use Cms\Post\Infrastructure\Controller\PostState;
use Cms\Post\Infrastructure\Controller\PostUrl;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Infrastructure\Controller\TermState;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Infrastructure\Controller\NamesResources;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostEditorTest extends TestCase
{
    use NamesResources;

    use ClockSensitiveTrait;

    private const string ID = PostIdMother::DEFAULT_VALUE;

    private const string SITE = RouteNameUrlGenerator::SITE;

    private const string PREVIEW = 'https://cms.blogautista.localhost/preview/posts/x?signature=firmada';

    private const string OTHER_SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a09';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItNamesWhatIsEditedAndWhenItChanged(): void
    {
        $editor = $this->edit(PostMother::create());

        PageComponents::one($editor, 'Shared:PageTitle', ['title' => 'cms.posts.edit.title', 'subtitle' => 'cms.posts.edit.dates(%created%=7 nov 2026, %updated%=7 nov 2026)', 'status' => 'cms.posts.status.draft.label']);
        self::assertSame(PostMother::TITLE.' · cms.posts.edit.title · CMS', $editor->title);
    }

    public function testItOffersThePreviewThroughTheSharedLink(): void
    {
        $editor = $this->edit(PostMother::create());

        PageComponents::one($editor, 'Shared:Modal', ['id' => 'preview', 'trigger' => 'cms.posts.preview.trigger', 'title' => 'cms.posts.preview.title', 'description' => 'cms.posts.preview.description', 'variant' => 'secondary']);
        PageComponents::one($editor, 'Shared:CopyField', [
            'id' => 'post-shared-preview-url',
            'label' => 'cms.posts.preview.url_label',
            'value' => self::PREVIEW,
            'hint' => 'cms.posts.preview.url_hint(%expires%=7 de noviembre de 2026, 10:00)',
            'copyLabel' => 'cms.posts.preview.copy',
            'copiedMessage' => 'cms.posts.preview.copied',
            'failedMessage' => 'cms.posts.preview.copy_failed',
        ]);
        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => self::PREVIEW, 'label' => 'cms.posts.preview.open']);
    }

    #[DataProvider('addressesOnTheSite')]
    public function testItLeadsToThePostWhereTheSiteServesIt(Post $post, ?string $url): void
    {
        $links = PageComponents::find($this->edit($post), 'Shared:ButtonLink', ['label' => 'cms.posts.edit.view_on_site']);

        self::assertSame(null === $url ? [] : [$url], array_map(static fn (PageComponent $link): mixed => $link->props['url'], $links));
    }

    /**
     * @return iterable<string, array{Post, ?string}>
     */
    public static function addressesOnTheSite(): iterable
    {
        $moved = PostMother::published(firstPublishedAt: '2026-10-01 10:00:00');
        $moved->edit(PostMother::TITLE, 'otro-slug', PostMother::SECTION, PostMother::AUTHOR, [], PostMother::CONTENT, PostMother::META_DESCRIPTION, 'index,follow', new InMemoryClassifiers());

        yield 'a published post, at its address there' => [$moved, self::SITE.'/vida-diaria/2026/10/01/'.PostMother::SLUG.'.html'];
        yield 'a draft' => [PostMother::publishable(), null];
        yield 'an unpublished post' => [PostMother::unpublished(), null];
    }

    /**
     * @param array{discard: bool, unpublish: bool, delete: bool} $offered
     */
    #[DataProvider('actionsByState')]
    public function testItOffersOnlyTheActionsThePostAllows(Post $post, array $offered): void
    {
        $editor = $this->edit($post);

        self::assertSame($offered, [
            'discard' => $this->confirms($editor, 'cms_posts_discard', 'discard', 'secondary'),
            // Taking it off the site is asked on a page of its own.
            'unpublish' => [] !== PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.posts.unpublish.trigger']),
            'delete' => $this->confirms($editor, 'cms_posts_delete', 'delete', 'danger'),
        ]);
    }

    /**
     * @return iterable<string, array{Post, array{discard: bool, unpublish: bool, delete: bool}}>
     */
    public static function actionsByState(): iterable
    {
        yield 'a draft' => [PostMother::create(), ['discard' => false, 'unpublish' => false, 'delete' => true]];
        yield 'a published post as it is on the site' => [PostMother::published(), ['discard' => false, 'unpublish' => true, 'delete' => false]];
        yield 'a published post with changes' => [PostMother::published(publishedTitle: 'Antes'), ['discard' => true, 'unpublish' => true, 'delete' => false]];
        yield 'an unpublished post' => [PostMother::unpublished(), ['discard' => false, 'unpublish' => false, 'delete' => true]];
    }

    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Post $post, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit($post);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'post-publish-form', 'waitsFor' => 'post-form', 'waitingHint' => 'cms.posts.edit.publish_unsaved']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn (PageComponent $button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.posts.edit.submit', 'variant' => $saveVariant]);
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_posts_publish?id='.self::ID, 'method' => 'post', 'id' => 'post-publish-form', 'hidden' => true, 'csrfTokenId' => 'cms_posts_publish']);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => 'cms.posts.status.'.match (true) {
            null === $publishLabel => 'published',
            'cms.posts.edit.republish' === $publishLabel => 'changed',
            default => 'draft',
        }.'.description']);
    }

    /**
     * @return iterable<string, array{Post, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [PostMother::create(), 'cms.posts.edit.publish', 'secondary'];
        yield 'a published post as it is on the site' => [PostMother::published(), null, 'primary'];
        yield 'a published post with changes' => [PostMother::published(publishedTitle: 'Antes'), 'cms.posts.edit.republish', 'secondary'];
    }

    public function testItEditsTheTitleAndTheSlugUnderTheAddressThePostWouldHave(): void
    {
        $editor = $this->edit(PostMother::publishable());

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_posts_update?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_posts_update', 'id' => 'post-form']);
        PageComponents::one($form, 'Shared:FormLabel', ['for' => 'post-title', 'text' => 'cms.posts.title_label']);
        $title = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'title', 'id' => 'post-title', 'value' => PostMother::TITLE, 'singleLine' => true, 'recommendedMax' => SearchPreview::TITLE_LIMIT]);
        self::assertSame(['required' => true, 'minlength' => Title::MIN_LENGTH, 'maxlength' => Title::MAX_LENGTH, 'aria-describedby' => 'post-title-count'], $title->props['attributes']);
        PageComponents::one($form, 'Shared:FormLabel', ['for' => 'post-slug', 'text' => 'cms.posts.edit.slug_label']);
        PageComponents::one($form, 'Shared:FormHint', ['id' => 'post-slug-hint', 'text' => 'cms.posts.edit.slug_hint']);
        $slug = PageComponents::one($form, 'Shared:FormInput', ['name' => 'slug', 'id' => 'post-slug', 'value' => PostMother::SLUG, 'prefix' => '/vida-diaria/2026/11/07/', 'suffix' => '.html']);
        self::assertSame([
            'minlength' => Slug::MIN_LENGTH,
            'maxlength' => Slug::MAX_LENGTH,
            'pattern' => Slug::SHAPE,
            'autocapitalize' => 'none',
            'spellcheck' => 'false',
            'aria-describedby' => 'post-slug-hint',
        ], $slug->props['attributes']);
    }

    #[DataProvider('prefixes')]
    public function testItAddressesThePostUnderItsSectionOnTheDayItWasOrWouldBeFirstPublished(Post $post, string $prefix): void
    {
        PageComponents::one($this->edit($post), 'Shared:FormInput', ['name' => 'slug', 'prefix' => $prefix]);
    }

    /**
     * @return iterable<string, array{Post, string}>
     */
    public static function prefixes(): iterable
    {
        $moved = PostMother::published(sectionSlug: 'antes', firstPublishedAt: '2026-10-01 10:00:00');
        $moved->edit(PostMother::TITLE, PostMother::SLUG, self::OTHER_SECTION, PostMother::AUTHOR, [], PostMother::CONTENT, PostMother::META_DESCRIPTION, 'index,follow', new InMemoryClassifiers(sections: [self::OTHER_SECTION => null]));

        yield 'a draft, under its section, today' => [PostMother::publishable(), '/vida-diaria/2026/11/07/'];
        yield 'an unfiled draft' => [PostMother::create(), '/cms.posts.edit.no_section/2026/11/07/'];
        yield 'a post on the site, at its address there' => [$moved, '/antes/2026/10/01/'];
        yield 'an unpublished post, on the day it was first published' => [PostMother::unpublished(firstPublishedAt: '2026-10-01 10:00:00'), '/vida-diaria/2026/10/01/'];
    }

    public function testItDatesADraftByTheDayOfMadrid(): void
    {
        self::mockTime('2026-11-14 23:30:00 UTC');

        PageComponents::one($this->edit(PostMother::publishable()), 'Shared:FormInput', ['name' => 'slug', 'prefix' => '/vida-diaria/2026/11/15/']);
    }

    public function testItPicksTheSectionAndTheAuthorFromAllOfThemSayingWhichAreOffTheSite(): void
    {
        $editor = $this->edit(PostMother::publishable());

        $panel = PageComponents::one($editor, 'Shared:Panel', ['title' => 'cms.posts.classification.title', 'description' => 'cms.posts.classification.description']);
        foreach (['section', 'author'] as $field) {
            PageComponents::one($panel, 'Shared:FormLabel', ['for' => 'post-'.$field, 'text' => 'cms.posts.classification.'.$field.'_label']);
            PageComponents::one($panel, 'Shared:FormHint', ['id' => 'post-'.$field.'-hint', 'text' => 'cms.posts.classification.'.$field.'_hint']);
        }
        $section = PageComponents::one($panel, 'Shared:FormSelect', ['name' => 'section', 'id' => 'post-section', 'value' => PostMother::SECTION, 'none' => 'cms.posts.classification.section_none']);
        self::assertSame(['aria-describedby' => 'post-section-hint'], $section->props['attributes']);
        self::assertSame([
            ['value' => PostMother::SECTION, 'label' => TermMother::NAME],
            ['value' => self::OTHER_SECTION, 'label' => 'Salud · cms.terms.status.draft.label'],
        ], $section->props['options']);
        $author = PageComponents::one($panel, 'Shared:FormSelect', ['name' => 'author', 'id' => 'post-author', 'value' => PostMother::AUTHOR, 'none' => 'cms.posts.classification.author_none']);
        self::assertSame([['value' => PostMother::AUTHOR, 'label' => AuthorMother::NAME.' · cms.authors.status.draft.label']], $author->props['options']);
    }

    public function testItLeavesTheSectionAndTheAuthorUnpickedOnAnUnfiledPost(): void
    {
        $editor = $this->edit(PostMother::create());

        PageComponents::one($editor, 'Shared:FormSelect', ['name' => 'section', 'value' => '']);
        PageComponents::one($editor, 'Shared:FormSelect', ['name' => 'author', 'value' => '']);
    }

    public function testItListsTheTagsOfThePostSayingWhichTheSiteDoesNotShow(): void
    {
        $editor = $this->edit(PostMother::publishable(tags: [PostMother::TAG, PostMother::OTHER_TAG]));

        PageComponents::one($editor, 'Shared:TermsField', [
            'name' => 'tags',
            'id' => 'post-tags',
            'label' => 'cms.posts.classification.tags_label',
            'hint' => 'cms.posts.classification.tags_hint',
            'items' => [
                ['id' => PostMother::TAG, 'name' => 'Sensorialidad', 'status' => 'cms.terms.status.published.label', 'note' => ''],
                ['id' => PostMother::OTHER_TAG, 'name' => 'Ruido', 'status' => 'cms.terms.status.draft.label', 'note' => 'cms.posts.classification.tag_off_site'],
            ],
            'searchUrl' => '/cms_terms_search?taxonomy=tags',
            'offSite' => 'cms.posts.classification.tag_off_site',
        ]);
    }

    public function testItEditsTheContentWithEveryOptionOfAPage(): void
    {
        $editor = $this->edit(PostMother::publishable());

        PageComponents::one($editor, 'Shared:RichTextEditor', [
            'name' => 'content',
            'id' => 'post-content-editor',
            'label' => 'cms.posts.edit.content_title',
            'placeholder' => 'cms.posts.edit.content_placeholder',
            'value' => json_encode(new TiptapRichText()->toTiptap(PostMother::CONTENT), \JSON_THROW_ON_ERROR),
            'features' => Post::contentFeatures()->toArray(),
            'imageSearchUrl' => '/cms_images_search',
            'imageLookupUrl' => '/cms_images_lookup',
            'pageSearchUrl' => '/cms_pages_search',
            'pageLookupUrl' => '/cms_pages_lookup',
        ]);
    }

    public function testItPreviewsTheSearchResultAtTheAddressThePostWouldHave(): void
    {
        $editor = $this->edit(PostMother::publishable());

        $panel = PageComponents::one($editor, 'Shared:Panel', ['title' => 'cms.posts.seo.title', 'description' => 'cms.posts.seo.description', 'variant' => 'disclosure']);
        PageComponents::one($panel, 'Shared:SearchPreview', [
            'label' => 'cms.posts.seo.preview',
            'title' => PostMother::TITLE,
            'url' => self::SITE.'/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html',
            'address' => self::SITE.'/vida-diaria/2026/11/07/%slug%.html',
            'description' => PostMother::META_DESCRIPTION,
            'emptyDescription' => 'cms.posts.seo.preview_empty',
            'sources' => ['title' => 'post-title', 'description' => 'post-meta-description', 'slug' => 'post-slug'],
        ]);
        PageComponents::one($panel, 'Shared:FormLabel', ['for' => 'post-meta-description', 'text' => 'cms.posts.seo.meta_description_label']);
        PageComponents::one($panel, 'Shared:FormHint', ['id' => 'post-meta-description-hint', 'text' => 'cms.posts.edit.meta_description_hint']);
        $description = PageComponents::one($panel, 'Shared:FormTextarea', [
            'name' => 'meta_description',
            'id' => 'post-meta-description',
            'value' => PostMother::META_DESCRIPTION,
            'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH,
            'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH,
        ]);
        self::assertSame(['maxlength' => Seo::DESCRIPTION_MAX_LENGTH, 'aria-describedby' => 'post-meta-description-hint post-meta-description-count'], $description->props['attributes']);
        $robots = PageComponents::one($panel, 'Shared:FormRadioGroup', ['name' => 'robots', 'id' => 'post-robots', 'legend' => 'cms.posts.seo.robots_legend', 'value' => 'index,follow']);
        self::assertSame(array_map(static fn (Robots $robots): array => [
            'value' => $robots->value,
            'label' => 'cms.posts.seo.robots.'.$robots->value.'.label',
            'hint' => 'cms.posts.seo.robots.'.$robots->value.'.hint',
        ], Robots::cases()), $robots->props['options']);
    }

    public function testItPreviewsAPostWithoutASlugAtTheAddressOfNone(): void
    {
        PageComponents::one($this->edit(PostMother::create(slug: '')), 'Shared:SearchPreview', ['url' => self::SITE.'/cms.posts.edit.no_section/2026/11/07/.html']);
    }

    private function confirms(Page $editor, string $route, string $action, string $variant): bool
    {
        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => \sprintf('/%s?id=%s', $route, self::ID), 'method' => 'post', 'csrfTokenId' => $route]);

        foreach ($forms as $form) {
            PageComponents::one($form, 'Shared:FormButton', ['label' => \sprintf('cms.posts.%s.submit', $action), 'variant' => 'danger']);
            PageComponents::one($editor, 'Shared:Modal', [
                'id' => $action.'-post',
                'trigger' => \sprintf('cms.posts.%s.trigger', $action),
                'title' => \sprintf('cms.posts.%s.title', $action),
                'description' => \sprintf('cms.posts.%s.description(%%title%%=%s)', $action, PostMother::TITLE),
                'variant' => $variant,
            ]);
        }

        return [] !== $forms;
    }

    private function edit(Post $post): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new PostEditor(
            $urlGenerator,
            $translator,
            new TiptapRichText(),
            new PostState($translator),
            new PostUrl($urlGenerator),
            new TermState($translator),
            new AuthorState($translator),
            $this->lastAddressPanelOnSite($translator, $urlGenerator),
        )(
            PostResponse::fromPost($post),
            [
                TermResponse::fromTerm(TermMother::published(id: PostMother::SECTION)),
                TermResponse::fromTerm(TermMother::create(id: self::OTHER_SECTION, name: 'Salud', slug: 'salud')),
            ],
            [AuthorResponse::fromAuthor(AuthorMother::create(id: PostMother::AUTHOR))],
            [
                TermResponse::fromTerm(TermMother::published(id: PostMother::TAG, taxonomy: Taxonomy::Tag, name: 'Sensorialidad', slug: 'sensorialidad')),
                TermResponse::fromTerm(TermMother::create(id: PostMother::OTHER_TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido')),
            ],
            self::PREVIEW,
            ClockMother::now(),
        );
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }
}
