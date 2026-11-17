<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Infrastructure\Controller\ExternalResourceEditor;
use Cms\External\Infrastructure\Controller\ExternalResourceState;
use Cms\Shared\Infrastructure\Http\ExternalImageUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Http\SignedUrls;
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
 * What the editor of an external resource is made of: what it is and where
 * it stands, its fields, those of its kind among them, its image, whether
 * its address still leads to it, and the actions the resource allows.
 */
final class ExternalResourceEditorTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string ID = ExternalResourceMother::ID;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItNamesWhatIsEditedWhatItIsAndWhenItChanged(): void
    {
        $editor = $this->edit(ExternalResourceMother::video());

        PageComponents::one($editor, 'Shared:PageTitle', [
            'title' => 'cms.externals.edit.title',
            'subtitle' => 'cms.externals.kind_by(%kind%=cms.externals.kind.video, %provider%=cms.externals.provider.youtube) · cms.externals.edit.dates(%created%=7 nov 2026, %updated%=7 nov 2026)',
            'status' => 'cms.externals.status.draft.label',
        ]);
        self::assertSame('Un vídeo · cms.externals.edit.title · CMS', $editor->title);
    }

    public function testItNamesAResourceNoProviderEmbedsByItsKindAlone(): void
    {
        PageComponents::one($this->edit(ExternalResourceMother::article()), 'Shared:PageTitle', [
            'subtitle' => 'cms.externals.kind.article · cms.externals.edit.dates(%created%=7 nov 2026, %updated%=7 nov 2026)',
        ]);
    }

    /**
     * Only a resource on the site whose address no longer leads to it
     * warns; one off the site is no concern of readers.
     */
    #[DataProvider('availabilities')]
    public function testItWarnsOnlyWhenAResourceOnTheSiteIsGone(ExternalResource $resource, int $alerts, string $status): void
    {
        $editor = $this->edit($resource);

        self::assertCount($alerts, PageComponents::find($editor, 'Shared:Alert', ['title' => 'cms.externals.edit.unavailable.title', 'message' => 'cms.externals.edit.unavailable.message']));
        PageComponents::one($editor, 'Shared:PageTitle', ['status' => $status]);
    }

    /**
     * @return iterable<string, array{ExternalResource, int, string}>
     */
    public static function availabilities(): iterable
    {
        yield 'on the site and gone' => [ExternalResourceMother::video(status: ResourceStatus::Published, availability: Availability::Unavailable), 1, 'cms.externals.status.unavailable.label'];
        yield 'on the site and there' => [ExternalResourceMother::video(status: ResourceStatus::Published), 0, 'cms.externals.status.published.label'];
        yield 'a draft gone' => [ExternalResourceMother::video(availability: Availability::Unavailable), 0, 'cms.externals.status.draft.label'];
    }

    public function testItEditsTheAddressTitleSummaryAndLanguage(): void
    {
        $form = $this->form($this->edit(ExternalResourceMother::article()));

        self::assertSame(['action' => '/cms_externals_update?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_externals_update', 'id' => 'external-form'], $form->props);
        self::assertSame(['url', 'title', 'summary', 'language', 'publisher', 'published_on'], self::fields($form));
        PageComponents::one($form, 'Shared:FormInput', ['name' => 'url', 'id' => 'external-url', 'type' => 'url', 'value' => ExternalResourceMother::ARTICLE_URL, 'attributes' => ['required' => true, 'maxlength' => 2000, 'spellcheck' => 'false', 'aria-describedby' => 'external-url-hint']]);
        PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'title', 'value' => 'Una noticia', 'singleLine' => true, 'attributes' => ['required' => true, 'minlength' => 3, 'maxlength' => 200, 'aria-describedby' => 'external-title-hint']]);
        PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'summary', 'value' => 'De qué trata.', 'attributes' => ['maxlength' => 300, 'aria-describedby' => 'external-summary-hint']]);
        PageComponents::one($form, 'Shared:FormSelect', ['name' => 'language', 'value' => 'es', 'none' => null, 'options' => array_map(
            static fn (string $language): array => ['value' => $language, 'label' => 'cms.externals.language.'.$language],
            ['es', 'en', 'ca', 'gl', 'eu', 'fr', 'pt', 'it', 'de'],
        )]);
        PageComponents::one($form, 'Shared:FormLabel', ['for' => 'external-url', 'text' => 'cms.externals.url_label']);
        PageComponents::one($form, 'Shared:FormHint', ['id' => 'external-url-hint', 'text' => 'cms.externals.edit.url_hint']);
    }

    public function testItLeavesASummaryEmptyWhenTheResourceHasNone(): void
    {
        PageComponents::one($this->form($this->edit(ExternalResourceMother::video())), 'Shared:FormTextarea', ['name' => 'summary', 'value' => '']);
    }

    /**
     * Each kind has the details its card or its facade tells; a post has
     * no summary, its text says what it is.
     *
     * @param list<string> $fields
     */
    #[DataProvider('kinds')]
    public function testItEditsTheDetailsOfTheKindOfTheResource(ExternalResource $resource, array $fields): void
    {
        self::assertSame($fields, self::fields($this->form($this->edit($resource))));
    }

    /**
     * @return iterable<string, array{ExternalResource, list<string>}>
     */
    public static function kinds(): iterable
    {
        yield 'a video' => [ExternalResourceMother::video(), ['url', 'title', 'summary', 'language']];
        yield 'a post' => [ExternalResourceMother::post(), ['url', 'title', 'language', 'author', 'handle', 'posted_on', 'text', 'with_media']];
        yield 'an audio' => [self::audio(), ['url', 'title', 'summary', 'language', 'show']];
        yield 'an article' => [ExternalResourceMother::article(), ['url', 'title', 'summary', 'language', 'publisher', 'published_on']];
        yield 'a study' => [ExternalResourceMother::study(), ['url', 'title', 'summary', 'language', 'authors', 'journal', 'year']];
        yield 'a document' => [self::document(), ['url', 'title', 'summary', 'language', 'publisher', 'bytes', 'pages']];
    }

    public function testItEditsTheDetailsOfAPostAsWhatEachIs(): void
    {
        $form = $this->form($this->edit(ExternalResourceMother::post()));

        PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'details[author]', 'id' => 'external-author', 'value' => 'Bluesky', 'singleLine' => true, 'attributes' => ['maxlength' => 100, 'aria-describedby' => 'external-author-hint']]);
        PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'details[handle]', 'value' => '@bsky.app', 'singleLine' => true, 'attributes' => ['maxlength' => 100, 'aria-describedby' => 'external-handle-hint']]);
        // A day of the past, at most today.
        PageComponents::one($form, 'Shared:FormInput', ['name' => 'details[posted_on]', 'id' => 'external-posted_on', 'type' => 'date', 'value' => '2024-10-17', 'attributes' => ['max' => '2026-11-07', 'aria-describedby' => 'external-posted_on-hint']]);
        PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'details[text]', 'value' => "Hola.\n\nAdiós.", 'rows' => 8, 'attributes' => ['maxlength' => 3000, 'aria-describedby' => 'external-text-hint']]);
        PageComponents::one($form, 'Shared:FormRadioGroup', ['name' => 'details[with_media]', 'id' => 'external-with_media', 'legend' => 'cms.externals.details.with_media.label', 'value' => '0', 'options' => [
            ['value' => '1', 'label' => 'cms.externals.details.with_media.yes', 'hint' => ''],
            ['value' => '0', 'label' => 'cms.externals.details.with_media.no', 'hint' => ''],
        ]]);
        PageComponents::one($form, 'Shared:FormLabel', ['for' => 'external-author', 'text' => 'cms.externals.details.author.label']);
        PageComponents::one($form, 'Shared:FormHint', ['id' => 'external-author-hint', 'text' => 'cms.externals.details.author.hint']);
    }

    public function testItTellsAPostWithItsPhotosByTheChoiceForYes(): void
    {
        $post = ExternalResourceMother::post(details: ['with_media' => true] + ExternalResourceMother::POST_DETAILS);

        PageComponents::one($this->form($this->edit($post)), 'Shared:FormRadioGroup', ['name' => 'details[with_media]', 'value' => '1']);
    }

    /**
     * A year within those a study can have; a size and a count of pages
     * with no ceiling, so none is told.
     */
    public function testItEditsTheNumbersOfAResourceWithinWhatTheyCanBe(): void
    {
        $study = $this->form($this->edit(ExternalResourceMother::study()));
        $document = $this->form($this->edit(self::document()));

        PageComponents::one($study, 'Shared:FormInput', ['name' => 'details[year]', 'type' => 'number', 'value' => '2013', 'attributes' => ['min' => 1600, 'max' => 2100, 'step' => 1, 'inputmode' => 'numeric', 'aria-describedby' => 'external-year-hint']]);
        PageComponents::one($study, 'Shared:FormTextarea', ['name' => 'details[journal]', 'value' => 'Nature', 'attributes' => ['maxlength' => 200, 'aria-describedby' => 'external-journal-hint']]);
        PageComponents::one($document, 'Shared:FormInput', ['name' => 'details[bytes]', 'value' => '16', 'attributes' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric', 'aria-describedby' => 'external-bytes-hint']]);
        PageComponents::one($document, 'Shared:FormInput', ['name' => 'details[pages]', 'value' => '', 'attributes' => ['min' => 1, 'step' => 1, 'inputmode' => 'numeric', 'aria-describedby' => 'external-pages-hint']]);
    }

    /**
     * Publishing takes what is saved: its form is apart, its button waits
     * while the edit form holds unsaved changes, and saving is the main
     * action only when there is nothing to publish.
     *
     * @param list<array<string, mixed>> $buttons
     */
    #[DataProvider('publications')]
    public function testItSavesAndOffersToPublishWhatThereIsToPublish(ExternalResource $resource, array $buttons): void
    {
        $editor = $this->edit($resource);
        $bar = PageComponents::one($this->form($editor), 'Shared:ActionBar', []);

        self::assertSame($buttons, array_map(static fn (PageComponent $button): array => $button->props, $bar->children));
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_externals_publish?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_externals_publish', 'id' => 'external-publish-form', 'hidden' => true]);
    }

    /**
     * @return iterable<string, array{ExternalResource, list<array<string, mixed>>}>
     */
    public static function publications(): iterable
    {
        $publish = static fn (string $label): array => ['label' => $label, 'form' => 'external-publish-form', 'waitsFor' => 'external-form', 'waitingHint' => 'cms.externals.edit.publish_unsaved'];
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Antes', [], ExternalResourceMother::IMAGE);

        yield 'a draft' => [ExternalResourceMother::video(), [['label' => 'cms.externals.edit.submit', 'variant' => 'secondary'], $publish('cms.externals.edit.publish')]];
        yield 'a resource on the site with changes' => [ExternalResourceMother::video(status: ResourceStatus::Published, published: $onSite), [['label' => 'cms.externals.edit.submit', 'variant' => 'secondary'], $publish('cms.externals.edit.republish')]];
        yield 'a resource on the site as it is' => [ExternalResourceMother::video(status: ResourceStatus::Published), [['label' => 'cms.externals.edit.submit', 'variant' => 'primary']]];
    }

    public function testItTellsWhereTheResourceStandsInTheBarOfTheForm(): void
    {
        PageComponents::one($this->form($this->edit(ExternalResourceMother::video())), 'Shared:ActionBar', ['status' => 'cms.externals.status.draft.description']);
    }

    /**
     * Opening it where it is and seeing what uses it are always offered;
     * discarding, unpublishing and deleting only when the resource allows
     * each, every one confirmed first.
     *
     * @param list<string> $modals
     */
    #[DataProvider('actions')]
    public function testItOffersTheActionsTheResourceAllows(ExternalResource $resource, array $modals): void
    {
        $title = PageComponents::one($this->edit($resource), 'Shared:PageTitle', []);
        $children = $title->children;

        self::assertSame([
            ['url' => $resource->version()->url()->value(), 'label' => 'cms.externals.edit.open'],
            ['url' => '/cms_relations?type=externals&id='.self::ID, 'label' => 'cms.externals.edit.relations'],
        ], array_map(static fn (PageComponent $link): array => $link->props, \array_slice($children, 0, 2)));
        self::assertSame($modals, array_map(static fn (PageComponent $modal): string => self::text($modal->props['id']).' '.self::text($modal->props['variant']), \array_slice($children, 2)));
    }

    /**
     * @return iterable<string, array{ExternalResource, list<string>}>
     */
    public static function actions(): iterable
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Antes', [], ExternalResourceMother::IMAGE);

        yield 'a draft, which can be deleted' => [ExternalResourceMother::video(), ['delete-external danger']];
        yield 'a resource on the site as it is' => [ExternalResourceMother::video(status: ResourceStatus::Published), ['unpublish-external secondary']];
        yield 'a resource on the site with changes' => [ExternalResourceMother::video(status: ResourceStatus::Published, published: $onSite), ['discard-external secondary', 'unpublish-external secondary']];
        yield 'a resource taken off the site' => [ExternalResourceMother::video(status: ResourceStatus::Unpublished), ['delete-external danger']];
    }

    public function testItConfirmsEachActionOnTheResourceBeforeIt(): void
    {
        $modal = PageComponents::one($this->edit(ExternalResourceMother::video()), 'Shared:Modal', [
            'id' => 'delete-external',
            'trigger' => 'cms.externals.delete.trigger',
            'title' => 'cms.externals.delete.title',
            'description' => 'cms.externals.delete.description(%title%=Un vídeo)',
            'variant' => 'danger',
        ]);

        PageComponents::one($modal, 'Shared:Form', ['action' => '/cms_externals_delete?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_externals_delete']);
        PageComponents::one($modal, 'Shared:FormButton', ['label' => 'cms.externals.delete.submit', 'variant' => 'danger']);
    }

    /**
     * The image is read with the rest, never chosen by hand: shown for a
     * day through a signed link, at the size of its shape.
     */
    public function testItShowsTheImageReadFromTheResourceAndFillsItAgain(): void
    {
        $panel = $this->panel($this->edit(ExternalResourceMother::video()), 'cms.externals.fill.title');
        $signed = new SignedUrls(new UriSigner('secreto'))->sign(
            RouteNameUrlGenerator::SITE.'/cms_externals_image?id='.self::ID.'&file='.ExternalResourceMother::IMAGE,
            ClockMother::now('2026-11-08 10:00:00'),
        );

        self::assertSame('cms.externals.fill.description_image', $panel->props['description']);
        PageComponents::one($panel, 'Shared:ImagePreview', ['src' => $signed, 'alt' => 'cms.externals.fill.image_alt(%title%=Un vídeo)', 'width' => 1200, 'height' => 675]);
        PageComponents::one($panel, 'Shared:Form', ['action' => '/cms_externals_fill?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_externals_fill']);
        PageComponents::one($panel, 'Shared:FormButton', ['label' => 'cms.externals.fill.submit', 'variant' => 'secondary']);
    }

    public function testItTellsWhenAResourceThatShowsAnImageHasNone(): void
    {
        $panel = $this->panel($this->edit(ExternalResourceMother::video(image: null)), 'cms.externals.fill.title');

        PageComponents::one($panel, 'Shared:Notice', ['message' => 'cms.externals.fill.no_image']);
        self::assertSame([], PageComponents::find($panel, 'Shared:ImagePreview', []));
    }

    /**
     * A post shows no image: its facade is its text.
     */
    public function testItFillsAgainWithoutImageAResourceThatShowsNone(): void
    {
        $panel = $this->panel($this->edit(ExternalResourceMother::post()), 'cms.externals.fill.title');

        self::assertSame('cms.externals.fill.description', $panel->props['description']);
        self::assertSame(['Shared:Form'], array_map(static fn (PageComponent $child): string => $child->name, $panel->children));
    }

    #[DataProvider('checks')]
    public function testItTellsWhetherTheAddressStillLeadsToTheResourceAndChecksItAgain(ExternalResource $resource, string $description): void
    {
        $panel = $this->panel($this->edit($resource), 'cms.externals.check.title');

        self::assertSame($description, $panel->props['description']);
        PageComponents::one($panel, 'Shared:Form', ['action' => '/cms_externals_check?id='.self::ID, 'method' => 'post', 'csrfTokenId' => 'cms_externals_check']);
        PageComponents::one($panel, 'Shared:FormButton', ['label' => 'cms.externals.check.submit', 'variant' => 'secondary']);
    }

    /**
     * @return iterable<string, array{ExternalResource, string}>
     */
    public static function checks(): iterable
    {
        $url = ExternalResourceMother::VIDEO_URL;

        yield 'never checked' => [ExternalResourceMother::video(), 'cms.externals.check.available(%date%=cms.externals.check.never, %url%='.$url.')'];
        yield 'checked gone' => [self::checked(Availability::Unavailable), 'cms.externals.check.unavailable(%date%=7 de noviembre de 2026, 10:00, %url%='.$url.')'];
    }

    private function edit(ExternalResource $resource): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new ExternalResourceEditor(
            $urlGenerator,
            $translator,
            new ExternalResourceState($translator),
            new ExternalImageUrl($urlGenerator, new SignedUrls(new UriSigner('secreto'))),
        )(ExternalResourceResponse::fromResource($resource));
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }

    private function form(Page $editor): PageComponent
    {
        return PageComponents::one($editor, 'Shared:Form', ['id' => 'external-form']);
    }

    private function panel(Page $editor, string $title): PageComponent
    {
        return PageComponents::one($editor, 'Shared:Panel', ['title' => $title]);
    }

    /**
     * The names of the fields of the form, in order, as their ids tell them.
     *
     * @return list<string>
     */
    private static function fields(PageComponent $form): array
    {
        $names = [];

        foreach ($form->children as $child) {
            if ('Shared:FormField' === $child->name) {
                $names[] = substr(self::text($child->children[0]->props['for']), \strlen('external-'));
            } elseif ('Shared:FormRadioGroup' === $child->name) {
                $names[] = substr(self::text($child->props['id']), \strlen('external-'));
            }
        }

        return $names;
    }

    private static function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    private static function audio(): ExternalResource
    {
        return ExternalResourceMother::resource(self::ID, ResourceKind::Audio, 'spotify', ExternalResourceMother::version(ResourceKind::Audio, 'https://open.spotify.com/episode/512ojhOuo1ktJprKbVcKyQ', 'episode/512ojhOuo1ktJprKbVcKyQ', 'Un episodio', ['show' => 'Un programa'], ExternalResourceMother::IMAGE), ResourceStatus::Draft, null, Availability::Available);
    }

    private static function document(): ExternalResource
    {
        return ExternalResourceMother::resource(self::ID, ResourceKind::Document, null, ExternalResourceMother::version(ResourceKind::Document, 'https://www.boe.es/informe.pdf', null, 'Un informe', ['publisher' => 'BOE', 'bytes' => 16, 'pages' => null], null), ResourceStatus::Draft, null, Availability::Available);
    }

    private static function checked(Availability $availability): ExternalResource
    {
        $resource = ExternalResourceMother::video(status: ResourceStatus::Published, availability: $availability);
        $property = new \ReflectionProperty(ExternalResource::class, 'checkedAt');
        $property->setValue($resource, ClockMother::now());

        return $resource;
    }
}
