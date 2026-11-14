<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorName;
use Cms\Author\Infrastructure\Controller\AuthorEditor;
use Cms\Author\Infrastructure\Controller\AuthorState;
use Cms\Author\Infrastructure\Controller\AvatarUrl;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Redirect\Infrastructure\Controller\NamesResources;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\SignedUrls;
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
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * The decisions the editor of an author makes: what it offers, where it
 * leads and what its form sends, its avatar and its bio among it.
 */
final class AuthorEditorTest extends TestCase
{
    use NamesResources;

    use ClockSensitiveTrait;

    private const string ID = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    private const string SITE = RouteNameUrlGenerator::SITE;

    private const string PREVIEW = 'https://cms.blogautista.localhost/preview/authors/x?signature=firmada';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItNamesWhatIsEditedAndWhenItChanged(): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:PageTitle', ['title' => 'cms.authors.edit.title', 'subtitle' => 'cms.authors.edit.dates(%created%=7 nov 2026, %updated%=7 nov 2026)']);
        self::assertSame(AuthorMother::NAME.' · cms.authors.edit.title · CMS', $editor->title);
    }

    public function testItOffersThePreviewThroughTheSharedLink(): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:Modal', ['id' => 'preview', 'trigger' => 'cms.authors.preview.trigger', 'variant' => 'secondary']);
        PageComponents::one($editor, 'Shared:CopyField', ['value' => self::PREVIEW, 'hint' => 'cms.authors.preview.url_hint(%expires%=7 de noviembre de 2026, 10:00)']);
        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => self::PREVIEW, 'label' => 'cms.authors.preview.open']);
    }

    #[DataProvider('addressesOnTheSite')]
    public function testItLeadsToTheAuthorWhereTheSiteServesIt(Author $author, ?string $url): void
    {
        $editor = $this->edit($author);

        $links = PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.authors.edit.view_on_site']);
        self::assertSame(null === $url ? [] : [$url], array_map(static fn (PageComponent $link): mixed => $link->props['url'], $links));
    }

    /**
     * @return iterable<string, array{Author, ?string}>
     */
    public static function addressesOnTheSite(): iterable
    {
        $moved = AuthorMother::published(id: self::ID);
        $moved->edit(AuthorMother::NAME, 'isaac-abreu', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');

        yield 'a published author, at its address there' => [$moved, self::SITE.'/autor/isaac.html'];
        yield 'a draft' => [AuthorMother::create(id: self::ID), null];
        yield 'an unpublished author' => [AuthorMother::unpublished(id: self::ID), null];
    }

    /**
     * Its editor leads to what it signs, on a page of its own.
     */
    public function testItLeadsToWhereItIsUsed(): void
    {
        PageComponents::one($this->edit(AuthorMother::create(id: self::ID)), 'Shared:ButtonLink', ['url' => '/cms_relations?type=authors&id='.self::ID]);
    }

    /**
     * @param array{discard: bool, unpublish: bool, delete: bool} $offered
     */
    #[DataProvider('actionsByState')]
    public function testItOffersOnlyTheActionsTheAuthorAllows(Author $author, array $offered): void
    {
        $editor = $this->edit($author);

        self::assertSame($offered, [
            'discard' => $this->confirms($editor, 'cms_authors_discard', 'discard'),
            // Taking it off the site is asked on a page of its own.
            'unpublish' => [] !== PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.authors.unpublish.trigger']),
            'delete' => $this->confirms($editor, 'cms_authors_delete', 'delete'),
        ]);
    }

    /**
     * @return iterable<string, array{Author, array{discard: bool, unpublish: bool, delete: bool}}>
     */
    public static function actionsByState(): iterable
    {
        yield 'a draft' => [AuthorMother::create(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
        yield 'a published author as it is on the site' => [AuthorMother::published(id: self::ID), ['discard' => false, 'unpublish' => true, 'delete' => false]];
        yield 'a published author with changes' => [AuthorMother::published(id: self::ID, publishedName: 'Antes'), ['discard' => true, 'unpublish' => true, 'delete' => false]];
        yield 'an unpublished author' => [AuthorMother::unpublished(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
    }

    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Author $author, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit($author);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'author-publish-form', 'waitsFor' => 'author-form', 'waitingHint' => 'cms.authors.edit.publish_unsaved']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn (PageComponent $button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.authors.edit.submit', 'variant' => $saveVariant]);
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_authors_publish?id='.self::ID, 'id' => 'author-publish-form', 'hidden' => true, 'csrfTokenId' => 'cms_authors_publish']);
    }

    /**
     * @return iterable<string, array{Author, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [AuthorMother::create(id: self::ID), 'cms.authors.edit.publish', 'secondary'];
        yield 'a published author as it is on the site' => [AuthorMother::published(id: self::ID), null, 'primary'];
        yield 'a published author with changes' => [AuthorMother::published(id: self::ID, publishedName: 'Antes'), 'cms.authors.edit.republish', 'secondary'];
    }

    /**
     * The avatar being edited is shown through a signed link, and a new one
     * may replace it; until one is uploaded, the form goes without one.
     */
    #[DataProvider('avatars')]
    public function testItShowsTheAvatarAndOffersToReplaceIt(?string $avatar, string $label): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID, avatar: $avatar));

        $panel = PageComponents::one($editor, 'Shared:Panel', ['title' => 'cms.authors.edit.avatar_title', 'description' => 'cms.authors.edit.avatar_description']);
        PageComponents::one($panel, 'Shared:ImageCropper', ['name' => 'avatar', 'id' => 'author-avatar', 'label' => $label, 'width' => 400, 'height' => 400, 'quality' => 0.85, 'required' => false]);
        $shown = PageComponents::find($panel, 'Shared:Avatar', ['srcset' => '', 'size' => 400, 'alt' => 'cms.authors.edit.avatar_current(%name%=Isaac Abreu)']);
        self::assertSame(null === $avatar ? [] : [true], array_map(
            // Absolute, naming the author and the file, and signed until the preview expires.
            static fn (PageComponent $image): bool => self::isSignedAvatarLink($image->props['src'], (string) $avatar),
            $shown,
        ));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function avatars(): iterable
    {
        yield 'without an avatar' => [null, 'cms.authors.edit.avatar_label'];
        yield 'with an avatar' => [AuthorMother::AVATAR, 'cms.authors.edit.avatar_replace'];
    }

    /**
     * The bio can use everything the content of a page can, linking any page
     * and embedding any image.
     */
    public function testItEditsTheBioWithEveryOptionOfAPage(): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID, bio: AuthorMother::BIO));

        PageComponents::one($editor, 'Shared:RichTextEditor', [
            'name' => 'bio',
            'id' => 'author-bio-editor',
            'label' => 'cms.authors.edit.bio_title',
            'placeholder' => 'cms.authors.edit.bio_placeholder',
            'value' => json_encode(new TiptapRichText()->toTiptap(AuthorMother::BIO), \JSON_THROW_ON_ERROR),
            'features' => Author::bioFeatures()->toArray(),
            'imageSearchUrl' => '/cms_images_search',
            'imageLookupUrl' => '/cms_images_lookup',
            'pageSearchUrl' => '/cms_pages_search',
            'pageLookupUrl' => '/cms_pages_lookup',
        ]);
    }

    public function testItEditsTheAddressOfTheAuthorUnderTheAuthors(): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID, slug: 'ana', metaDescription: 'Resumen'));

        PageComponents::one($editor, 'Shared:FormInput', ['name' => 'slug', 'value' => 'ana', 'prefix' => '/autor/', 'suffix' => '.html']);
        PageComponents::one($editor, 'Shared:SearchPreview', [
            'title' => AuthorMother::NAME,
            'url' => self::SITE.'/autor/ana.html',
            'address' => self::SITE.'/autor/%slug%.html',
            'description' => 'Resumen',
            'sources' => ['title' => 'author-name', 'description' => 'author-meta-description', 'slug' => 'author-slug'],
        ]);
    }

    public function testItSavesTheAuthorWithTheFieldsItsUpdateReads(): void
    {
        $editor = $this->edit(AuthorMother::create(id: self::ID, slug: ''));

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_authors_update?id='.self::ID, 'id' => 'author-form', 'csrfTokenId' => 'cms_authors_update', 'enctype' => 'multipart/form-data']);
        $name = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'name', 'value' => AuthorMother::NAME, 'singleLine' => true, 'recommendedMax' => SearchPreview::TITLE_LIMIT]);
        $slug = PageComponents::one($form, 'Shared:FormInput', ['name' => 'slug', 'value' => '']);
        $metaDescription = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'meta_description', 'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH, 'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH]);
        PageComponents::one($form, 'Shared:FormRadioGroup', [
            'name' => 'robots',
            'value' => 'index,follow',
            'options' => array_map(static fn (Robots $robots): array => [
                'value' => $robots->value,
                'label' => \sprintf('cms.authors.seo.robots.%s.label', $robots->value),
                'hint' => \sprintf('cms.authors.seo.robots.%s.hint', $robots->value),
            ], Robots::cases()),
        ]);
        self::assertSame(['required' => true, 'minlength' => AuthorName::MIN_LENGTH, 'maxlength' => AuthorName::MAX_LENGTH], $this->constraints($name, ['required', 'minlength', 'maxlength']));
        self::assertSame(['minlength' => Slug::MIN_LENGTH, 'maxlength' => Slug::MAX_LENGTH, 'pattern' => Slug::SHAPE], $this->constraints($slug, ['minlength', 'maxlength', 'pattern']));
        self::assertSame(['maxlength' => Seo::DESCRIPTION_MAX_LENGTH], $this->constraints($metaDescription, ['maxlength']));
    }

    #[DataProvider('authorsWhereverTheyStand')]
    public function testItTellsWhereTheAuthorStands(Author $author, string $state): void
    {
        $editor = $this->edit($author);

        PageComponents::one($editor, 'Shared:PageTitle', ['status' => \sprintf('cms.authors.status.%s.label', $state)]);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => \sprintf('cms.authors.status.%s.description', $state)]);
    }

    /**
     * @return iterable<string, array{Author, string}>
     */
    public static function authorsWhereverTheyStand(): iterable
    {
        yield 'a draft' => [AuthorMother::create(id: self::ID), 'draft'];
        yield 'a published author' => [AuthorMother::published(id: self::ID), 'published'];
        yield 'a published author with changes' => [AuthorMother::published(id: self::ID, publishedName: 'Antes'), 'changed'];
        yield 'an unpublished author' => [AuthorMother::unpublished(id: self::ID), 'unpublished'];
    }

    private static function isSignedAvatarLink(mixed $src, string $avatar): bool
    {
        self::assertIsString($src);

        return 1 === preg_match(\sprintf('#^%s/cms_authors_avatar\?.*_expiration=%d.*&_hash=.+#', self::SITE, ClockMother::now()->getTimestamp()), $src)
            && str_contains($src, 'file='.$avatar)
            && str_contains($src, 'id='.self::ID);
    }

    private function confirms(Page $editor, string $route, string $action): bool
    {
        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => \sprintf('/%s?id=%s', $route, self::ID), 'csrfTokenId' => $route]);

        foreach ($forms as $form) {
            PageComponents::one($form, 'Shared:FormButton', ['label' => \sprintf('cms.authors.%s.submit', $action), 'variant' => 'danger']);
            PageComponents::one($editor, 'Shared:Modal', [
                'id' => $action.'-author',
                'trigger' => \sprintf('cms.authors.%s.trigger', $action),
                'title' => \sprintf('cms.authors.%s.title', $action),
                'description' => \sprintf('cms.authors.%s.description(%%name%%=%s)', $action, AuthorMother::NAME),
                'variant' => 'delete' === $action ? 'danger' : 'secondary',
            ]);
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

    private function edit(Author $author): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new AuthorEditor(
            $urlGenerator,
            $translator,
            new TiptapRichText(),
            new AuthorState($translator),
            new AvatarUrl($urlGenerator, new SignedUrls(new UriSigner('secreto'))),
            $this->lastAddressPanelOnSite($translator, $urlGenerator),
        )(AuthorResponse::fromAuthor($author), self::PREVIEW, ClockMother::now());
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }
}
