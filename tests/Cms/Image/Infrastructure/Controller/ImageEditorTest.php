<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Caption;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageEditor;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\LastAddressPanel;
use Cms\Shared\Infrastructure\Publication\ReplacedRedirectNotice;
use Cms\Shared\Infrastructure\Ui\ImageState;
use Cms\Shared\Infrastructure\Ui\LinkTargets;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the editor of an image makes: what it offers, and where it
 * leads. Routes are generated as their names; the texts are their
 * translation keys, with what their placeholders are given.
 */
final class ImageEditorTest extends TestCase
{
    private const string ID = '01a1b801-ce58-723d-8234-b76d9105f001';

    /**
     * The image is shown as it is kept, at the size the site serves it,
     * described by its alternative text.
     */
    public function testItShowsTheImageItEdits(): void
    {
        $editor = $this->edit(ImageMother::create(id: self::ID, alt: 'Un faro al anochecer'));

        PageComponents::one($editor, 'Shared:ImagePreview', [
            'src' => '/cms_images_file?id='.self::ID,
            'alt' => 'Un faro al anochecer',
            'width' => Image::WIDTH,
            'height' => Image::HEIGHT,
        ]);
    }

    /**
     * When it was uploaded and last changed, in the language of the editor.
     */
    public function testItTellsWhenTheImageWasUploadedAndChanged(): void
    {
        $editor = $this->edit(ImageMother::create(id: self::ID, createdAt: '2026-10-01 09:00:00', updatedAt: '2026-11-09 18:30:00'));

        PageComponents::one($editor, 'Shared:PageTitle', ['subtitle' => 'cms.images.edit.dates(%updated%=9 nov 2026, %uploaded%=1 oct 2026)']);
    }

    public function testItLeadsToWhatUsesTheImage(): void
    {
        $editor = $this->edit(ImageMother::published(id: self::ID));

        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => '/cms_relations?type=images&id='.self::ID]);
    }

    /**
     * Each action on the image is offered only when the image allows it.
     *
     * @param array{discard: bool, unpublish: bool, delete: bool} $offered
     */
    #[DataProvider('actionsByState')]
    public function testItOffersOnlyTheActionsTheImageAllows(Image $image, array $offered): void
    {
        $editor = $this->edit($image);

        self::assertSame($offered, [
            'discard' => $this->confirms($editor, 'cms_images_discard'),
            'unpublish' => [] !== PageComponents::find($editor, 'Shared:ButtonLink', ['url' => '/cms_images_unpublish_form?id='.self::ID]),
            'delete' => $this->confirms($editor, 'cms_images_delete'),
        ]);
    }

    /**
     * @return iterable<string, array{Image, array{discard: bool, unpublish: bool, delete: bool}}>
     */
    public static function actionsByState(): iterable
    {
        yield 'a draft' => [ImageMother::create(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
        yield 'a published image as it is on the site' => [ImageMother::published(id: self::ID), ['discard' => false, 'unpublish' => true, 'delete' => false]];
        yield 'a published image with changes' => [
            ImageMother::published(id: self::ID, alt: 'Un faro', publishedAlt: 'Un faro al anochecer'),
            ['discard' => true, 'unpublish' => true, 'delete' => false],
        ];
        yield 'an unpublished image' => [ImageMother::unpublished(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
    }

    /**
     * Publishing is offered while it would change the site, worded for
     * whether the image is there already, and saving is the main action
     * otherwise.
     */
    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Image $image, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit($image);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'image-publish-form', 'waitsFor' => 'image-form']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn (PageComponent $button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.images.edit.submit', 'variant' => $saveVariant]);
        // It publishes what is saved, so it is a form of its own, out of sight.
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_images_publish?id='.self::ID, 'id' => 'image-publish-form', 'hidden' => true]);
    }

    /**
     * @return iterable<string, array{Image, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [ImageMother::create(id: self::ID), 'cms.images.edit.publish', 'secondary'];
        yield 'a published image as it is on the site' => [ImageMother::published(id: self::ID), null, 'primary'];
        yield 'a published image with changes' => [
            ImageMother::published(id: self::ID, alt: 'Un faro', publishedAlt: 'Un faro al anochecer'),
            'cms.images.edit.republish',
            'secondary',
        ];
        yield 'an unpublished image' => [ImageMother::unpublished(id: self::ID), 'cms.images.edit.publish', 'secondary'];
    }

    /**
     * Where the image stands is told by its badge and by the bar of its
     * actions; changes not on the site yet are what matters most.
     */
    #[DataProvider('imagesWhereverTheyStand')]
    public function testItTellsWhereTheImageStands(Image $image, string $state): void
    {
        $editor = $this->edit($image);

        PageComponents::one($editor, 'Shared:PageTitle', ['status' => \sprintf('cms.images.status.%s.label', $state)]);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => \sprintf('cms.images.status.%s.description', $state)]);
    }

    /**
     * @return iterable<string, array{Image, string}>
     */
    public static function imagesWhereverTheyStand(): iterable
    {
        yield 'a draft' => [ImageMother::create(id: self::ID), 'draft'];
        yield 'a published image as it is on the site' => [ImageMother::published(id: self::ID), 'published'];
        yield 'a published image with changes' => [ImageMother::published(id: self::ID, alt: 'Un faro', publishedAlt: 'Un faro al anochecer'), 'changed'];
        yield 'an unpublished image' => [ImageMother::unpublished(id: self::ID), 'unpublished'];
    }

    /**
     * The editor saves the image with the fields its update reads, held to
     * what the image accepts, so the browser refuses what the image would:
     * an alternative text on one line, and a caption of one paragraph that
     * may link pages.
     */
    public function testItSavesTheImageWithTheFieldsItsUpdateReads(): void
    {
        $editor = $this->edit(ImageMother::create(id: self::ID, alt: 'Un faro al anochecer', caption: ImageMother::CAPTION));

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_images_update?id='.self::ID, 'id' => 'image-form']);
        $alt = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'alt', 'value' => 'Un faro al anochecer', 'singleLine' => true]);
        self::assertSame(
            ['required' => true, 'maxlength' => Alt::MAX_LENGTH],
            array_intersect_key(self::attributes($alt), ['required' => 0, 'maxlength' => 0]),
        );
        PageComponents::one($form, 'Shared:RichTextEditor', [
            'name' => 'caption',
            'value' => json_encode(new TiptapRichText()->toTiptap(ImageMother::CAPTION), \JSON_THROW_ON_ERROR),
            'features' => Caption::features()->toArray(),
            'singleParagraph' => true,
            'maxLength' => Caption::MAX_LENGTH,
            'linkTargets' => new LinkTargets(new RouteNameUrlGenerator())(null),
        ]);
    }

    /**
     * An image without a caption is given an empty one to write.
     */
    public function testItOffersAnEmptyCaptionToAnImageWithoutOne(): void
    {
        $editor = $this->edit(ImageMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:RichTextEditor', [
            'name' => 'caption',
            'value' => json_encode(new TiptapRichText()->toTiptap(RichText::empty()->toArray()), \JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Whether the editor offers the action, confirmed first, and submitted
     * with a button of its own.
     */
    private function confirms(Page $editor, string $route): bool
    {
        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => \sprintf('/%s?id=%s', $route, self::ID)]);

        foreach ($forms as $form) {
            self::assertSame(['Shared:FormButton'], array_map(static fn (PageComponent $child): string => $child->name, $form->children));
        }

        return [] !== $forms;
    }

    /**
     * @return array<mixed>
     */
    private static function attributes(PageComponent $field): array
    {
        $attributes = $field->props['attributes'] ?? [];
        self::assertIsArray($attributes);

        return $attributes;
    }

    private function edit(Image $image): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('isOnSite')->willReturn(true);
        $redirectNames = new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $translator);

        $editor = new ImageEditor(
            $urlGenerator,
            $translator,
            new TiptapRichText(),
            new ImageState($translator),
            new LastAddressPanel(
                self::createStub(RedirectRepository::class),
                $siteResources,
                $redirectNames,
                $translator,
                $urlGenerator,
                new ReplacedRedirectNotice($redirectNames, $translator),
            ),
            new LinkTargets($urlGenerator),
        );

        $composed = $editor(ImageResponse::fromImage($image));
        // Whatever a test asks, the editor gives every component what it takes.
        ComponentContracts::assertHonoured($composed);

        return $composed;
    }
}
