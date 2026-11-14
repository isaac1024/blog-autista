<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermName;
use Cms\Taxonomy\Infrastructure\Controller\TaxonomySegment;
use Cms\Taxonomy\Infrastructure\Controller\TermAddress;
use Cms\Taxonomy\Infrastructure\Controller\TermEditor;
use Cms\Taxonomy\Infrastructure\Controller\TermState;
use Cms\Tests\Redirect\Infrastructure\Controller\NamesResources;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The decisions the editor of a term makes: what it offers, where it leads
 * and what its form sends. Routes are generated as their names; the texts
 * are their translation keys.
 */
final class TermEditorTest extends TestCase
{
    use NamesResources;

    use ClockSensitiveTrait;

    private const string ID = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    private const string SITE = RouteNameUrlGenerator::SITE;

    private const string PREVIEW = 'https://cms.blogautista.localhost/preview/sections/x?signature=firmada';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItNamesWhatIsEditedAndWhenItChanged(): void
    {
        $editor = $this->edit(TaxonomySegment::Tags, TermMother::create(id: self::ID, taxonomy: Taxonomy::Tag));

        PageComponents::one($editor, 'Shared:PageTitle', ['title' => 'cms.tags.edit.title', 'subtitle' => 'cms.terms.edit.dates(%created%=7 nov 2026, %updated%=7 nov 2026)']);
        self::assertSame(TermMother::NAME.' · cms.tags.edit.title · CMS', $editor->title);
    }

    public function testItOffersThePreviewThroughTheSharedLink(): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, TermMother::create(id: self::ID));

        PageComponents::one($editor, 'Shared:CopyField', ['value' => self::PREVIEW, 'hint' => 'cms.terms.preview.url_hint(%expires%=7 de noviembre de 2026, 10:00)']);
        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => self::PREVIEW, 'label' => 'cms.terms.preview.open']);
    }

    /**
     * The site serves the term at the slug it was published with, not at the
     * one edited since, and nowhere while it is off it.
     */
    #[DataProvider('addressesOnTheSite')]
    public function testItLeadsToTheTermWhereTheSiteServesIt(TaxonomySegment $segment, Term $term, ?string $url): void
    {
        $editor = $this->edit($segment, $term);

        $links = PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.terms.edit.view_on_site']);
        self::assertSame(null === $url ? [] : [$url], array_map(static fn (PageComponent $link): mixed => $link->props['url'], $links));
    }

    /**
     * @return iterable<string, array{TaxonomySegment, Term, ?string}>
     */
    public static function addressesOnTheSite(): iterable
    {
        yield 'a published section' => [TaxonomySegment::Sections, TermMother::published(id: self::ID, slug: 'dia-a-dia', publishedSlug: 'vida-diaria'), self::SITE.'/vida-diaria/'];
        yield 'a published tag' => [TaxonomySegment::Tags, TermMother::published(id: self::ID, taxonomy: Taxonomy::Tag, slug: 'sueno'), self::SITE.'/tag/sueno.html'];
        yield 'a draft' => [TaxonomySegment::Sections, TermMother::create(id: self::ID), null];
        yield 'an unpublished term' => [TaxonomySegment::Sections, TermMother::unpublished(id: self::ID), null];
    }

    /**
     * Each action on the term is offered only when the term allows it,
     * confirmed first.
     *
     * @param array{discard: bool, unpublish: bool, delete: bool} $offered
     */
    #[DataProvider('actionsByState')]
    public function testItOffersOnlyTheActionsTheTermAllows(Term $term, array $offered): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, $term);

        self::assertSame($offered, [
            'discard' => $this->confirms($editor, 'cms_terms_discard', 'discard'),
            // Taking it off the site is asked on a page of its own.
            'unpublish' => [] !== PageComponents::find($editor, 'Shared:ButtonLink', ['label' => 'cms.sections.unpublish.trigger']),
            'delete' => $this->confirms($editor, 'cms_terms_delete', 'delete'),
        ]);
    }

    /**
     * @return iterable<string, array{Term, array{discard: bool, unpublish: bool, delete: bool}}>
     */
    public static function actionsByState(): iterable
    {
        yield 'a draft' => [TermMother::create(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
        yield 'a published term as it is on the site' => [TermMother::published(id: self::ID), ['discard' => false, 'unpublish' => true, 'delete' => false]];
        yield 'a published term with changes' => [TermMother::published(id: self::ID, publishedName: 'Antes'), ['discard' => true, 'unpublish' => true, 'delete' => false]];
        yield 'an unpublished term' => [TermMother::unpublished(id: self::ID), ['discard' => false, 'unpublish' => false, 'delete' => true]];
    }

    /**
     * Publishing is offered while it would change the site, worded for
     * whether the term is there already, and saving is the main action
     * otherwise.
     */
    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Term $term, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, $term);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'term-publish-form', 'waitsFor' => 'term-form', 'waitingHint' => 'cms.terms.edit.publish_unsaved']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn (PageComponent $button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.terms.edit.submit', 'variant' => $saveVariant]);
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_terms_publish?taxonomy=sections&id='.self::ID, 'id' => 'term-publish-form', 'hidden' => true, 'csrfTokenId' => 'cms_terms_publish']);
    }

    /**
     * @return iterable<string, array{Term, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [TermMother::create(id: self::ID), 'cms.terms.edit.publish', 'secondary'];
        yield 'a published term as it is on the site' => [TermMother::published(id: self::ID), null, 'primary'];
        yield 'a published term with changes' => [TermMother::published(id: self::ID, publishedName: 'Antes'), 'cms.terms.edit.republish', 'secondary'];
        yield 'an unpublished term' => [TermMother::unpublished(id: self::ID), 'cms.terms.edit.publish', 'secondary'];
    }

    /**
     * The slug is written between what the address of the taxonomy has around
     * it, which search results preview as it is typed.
     */
    #[DataProvider('slugFields')]
    public function testItEditsTheAddressOfTheTermWhereTheTaxonomyServesIt(TaxonomySegment $segment, Taxonomy $taxonomy, string $prefix, string $suffix, string $url, string $address): void
    {
        $editor = $this->edit($segment, TermMother::create(id: self::ID, taxonomy: $taxonomy, slug: 'sueno'));

        PageComponents::one($editor, 'Shared:FormInput', ['name' => 'slug', 'value' => 'sueno', 'prefix' => $prefix, 'suffix' => $suffix]);
        PageComponents::one($editor, 'Shared:FormHint', ['id' => 'term-slug-hint', 'text' => \sprintf('cms.%s.edit.slug_hint', $segment->value)]);
        PageComponents::one($editor, 'Shared:SearchPreview', [
            'title' => TermMother::NAME,
            'url' => $url,
            'address' => $address,
            'sources' => ['title' => 'term-name', 'description' => 'term-meta-description', 'slug' => 'term-slug'],
        ]);
    }

    /**
     * @return iterable<string, array{TaxonomySegment, Taxonomy, string, string, string, string}>
     */
    public static function slugFields(): iterable
    {
        yield 'a section' => [TaxonomySegment::Sections, Taxonomy::Section, '/', '/', self::SITE.'/sueno/', self::SITE.'/%slug%/'];
        yield 'a tag' => [TaxonomySegment::Tags, Taxonomy::Tag, '/tag/', '.html', self::SITE.'/tag/sueno.html', self::SITE.'/tag/%slug%.html'];
    }

    public function testItPreviewsATermWithoutASlugAtTheAddressOfTheTaxonomy(): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, TermMother::create(id: self::ID, slug: ''));

        PageComponents::one($editor, 'Shared:FormInput', ['name' => 'slug', 'value' => '']);
        PageComponents::one($editor, 'Shared:SearchPreview', ['url' => self::SITE.'//']);
    }

    public function testItOffersEveryRobotsChoiceWithTheTermsChosen(): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, TermMother::create(id: self::ID, metaDescription: 'Resumen', robots: Robots::NoindexNofollow));

        PageComponents::one($editor, 'Shared:FormRadioGroup', [
            'name' => 'robots',
            'value' => 'noindex,nofollow',
            'options' => array_map(static fn (Robots $robots): array => [
                'value' => $robots->value,
                'label' => \sprintf('cms.terms.seo.robots.%s.label', $robots->value),
                'hint' => \sprintf('cms.terms.seo.robots.%s.hint', $robots->value),
            ], Robots::cases()),
        ]);
        PageComponents::one($editor, 'Shared:SearchPreview', ['description' => 'Resumen']);
    }

    /**
     * The editor saves the term with the fields its update reads, held to
     * what the term accepts, so the browser refuses what the term would.
     */
    public function testItSavesTheTermWithTheFieldsItsUpdateReads(): void
    {
        $editor = $this->edit(TaxonomySegment::Tags, TermMother::create(id: self::ID, taxonomy: Taxonomy::Tag));

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_terms_update?taxonomy=tags&id='.self::ID, 'id' => 'term-form', 'csrfTokenId' => 'cms_terms_update']);
        $name = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'name', 'value' => TermMother::NAME, 'singleLine' => true, 'recommendedMax' => SearchPreview::TITLE_LIMIT]);
        $slug = PageComponents::one($form, 'Shared:FormInput', ['name' => 'slug']);
        $metaDescription = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'meta_description', 'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH, 'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH]);
        PageComponents::one($form, 'Shared:FormRadioGroup', ['name' => 'robots']);
        self::assertSame(['required' => true, 'minlength' => TermName::MIN_LENGTH, 'maxlength' => TermName::MAX_LENGTH], $this->constraints($name, ['required', 'minlength', 'maxlength']));
        self::assertSame(['minlength' => Slug::MIN_LENGTH, 'maxlength' => Slug::MAX_LENGTH, 'pattern' => Slug::SHAPE], $this->constraints($slug, ['minlength', 'maxlength', 'pattern']));
        self::assertSame(['maxlength' => Seo::DESCRIPTION_MAX_LENGTH], $this->constraints($metaDescription, ['maxlength']));
    }

    #[DataProvider('termsWhereverTheyStand')]
    public function testItTellsWhereTheTermStands(Term $term, string $state): void
    {
        $editor = $this->edit(TaxonomySegment::Sections, $term);

        PageComponents::one($editor, 'Shared:PageTitle', ['status' => \sprintf('cms.terms.status.%s.label', $state)]);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => \sprintf('cms.terms.status.%s.description', $state)]);
    }

    /**
     * @return iterable<string, array{Term, string}>
     */
    public static function termsWhereverTheyStand(): iterable
    {
        yield 'a draft' => [TermMother::create(id: self::ID), 'draft'];
        yield 'a published term' => [TermMother::published(id: self::ID), 'published'];
        yield 'a published term with changes' => [TermMother::published(id: self::ID, publishedName: 'Antes'), 'changed'];
        yield 'an unpublished term' => [TermMother::unpublished(id: self::ID), 'unpublished'];
    }

    /**
     * Whether the editor offers the action, in a modal named for it, confirmed
     * with a button of its own.
     */
    private function confirms(Page $editor, string $route, string $action): bool
    {
        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => \sprintf('/%s?taxonomy=sections&id=%s', $route, self::ID), 'csrfTokenId' => $route]);

        foreach ($forms as $form) {
            PageComponents::one($form, 'Shared:FormButton', ['label' => \sprintf('cms.sections.%s.submit', $action), 'variant' => 'danger']);
            PageComponents::one($editor, 'Shared:Modal', [
                'id' => $action.'-term',
                'trigger' => \sprintf('cms.sections.%s.trigger', $action),
                'title' => \sprintf('cms.sections.%s.title', $action),
                'description' => \sprintf('cms.sections.%s.description(%%name%%=%s)', $action, TermMother::NAME),
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

    private function edit(TaxonomySegment $segment, Term $term): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new TermEditor($urlGenerator, $translator, new TermState($translator), new TermAddress($urlGenerator), $this->lastAddressPanelOnSite($translator, $urlGenerator))(
            $segment,
            TermResponse::fromTerm($term),
            self::PREVIEW,
            ClockMother::now(),
        );
        // Whatever a test asks, the editor gives every component what it takes.
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }
}
