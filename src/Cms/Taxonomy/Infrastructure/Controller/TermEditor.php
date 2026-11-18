<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Shared\Infrastructure\Publication\LastAddressPanel;
use Cms\Shared\Infrastructure\Ui\TermState;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\TermName;
use Cms\Taxonomy\Domain\TermStatus;
use Shared\Domain\SiteTimeZone;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of a term: where it stands and what is done to it
 * apart from editing, its fields, and the actions that save and publish it,
 * each offered only when the term allows it.
 *
 * Composing a page reaches every component it is made of, so its coupling
 * grows with the page and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class TermEditor
{
    private const string FORM = 'term-form';

    private const string PUBLISH_FORM = 'term-publish-form';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private TermState $termState,
        private TermAddress $termAddress,
        private LastAddressPanel $lastAddressPanel,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(TaxonomySegment $taxonomy, TermResponse $term, string $previewUrl, \DateTimeImmutable $previewExpiresAt): Page
    {
        return $this->lastAddressPanel->onTerm(CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $term->name,
            $this->translator->trans($taxonomy->key('edit.title')),
        ))
            // What is being edited and where it stands, with what is done to it apart from editing.
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans($taxonomy->key('edit.title')),
                'subtitle' => $this->dates($term),
                'status' => $this->termState->label($term),
            ], $this->termActions($taxonomy, $term, $previewUrl, $previewExpiresAt)), $term->id)
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_terms_update', ['taxonomy' => $taxonomy->value, 'id' => $term->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_terms_update',
                'id' => self::FORM,
            ], [
                $this->nameField($term),
                $this->slugField($taxonomy, $term),
                $this->searchEngineSection($taxonomy, $term),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->termState->description($term),
                ], $this->formActions($term)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_terms_publish', ['taxonomy' => $taxonomy->value, 'id' => $term->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_terms_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * What is done to the term apart from editing it, offered only when the
     * term allows it: previewing what is saved, through a link that expires
     * and can be sent to anyone, seeing it on the site, seeing what is filed
     * under it, taking it off the site, and, each confirmed first, discarding
     * its unpublished changes or deleting it.
     *
     * @return list<PageComponent>
     */
    private function termActions(TaxonomySegment $taxonomy, TermResponse $term, string $previewUrl, \DateTimeImmutable $previewExpiresAt): array
    {
        $actions = [$this->previewModal($previewUrl, $previewExpiresAt)];

        // The term as the site serves it, at the address it has there, which
        // may differ from the one being edited.
        if (null !== $term->publishedSlug && TermStatus::Published->value === $term->status) {
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->termAddress->address($taxonomy, $term->publishedSlug, UrlGeneratorInterface::ABSOLUTE_URL),
                'label' => $this->translator->trans('cms.terms.edit.view_on_site'),
            ]);
        }

        // What is filed under the term, on a page of its own.
        $actions[] = new PageComponent('Shared:ButtonLink', [
            'url' => $this->urlGenerator->generate('cms_relations', ['type' => $taxonomy->value, 'id' => $term->id]),
            'label' => $this->translator->trans('cms.terms.edit.relations'),
        ]);

        if ($term->changed) {
            $actions[] = $this->confirmModal($taxonomy, $term, 'discard', 'cms_terms_discard', 'secondary');
        }

        if ($term->canBeUnpublished) {
            // Taking it off the site asks what becomes of its addresses, on a
            // page of its own.
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_terms_unpublish_form', ['taxonomy' => $taxonomy->value, 'id' => $term->id]),
                'label' => $this->translator->trans($taxonomy->key('unpublish.trigger')),
            ]);
        }

        if ($term->canBeDeleted) {
            $actions[] = $this->confirmModal($taxonomy, $term, 'delete', 'cms_terms_delete', 'danger');
        }

        return $actions;
    }

    /**
     * The version last saved, as the site would serve it once published,
     * behind a signed link that shows it to anyone who has it, without
     * signing in, until it expires: the editor opens it, or copies it to send.
     */
    private function previewModal(string $previewUrl, \DateTimeImmutable $expiresAt): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT, SiteTimeZone::NAME);

        return new PageComponent('Shared:Modal', [
            'id' => 'preview',
            'trigger' => $this->translator->trans('cms.terms.preview.trigger'),
            'title' => $this->translator->trans('cms.terms.preview.title'),
            'description' => $this->translator->trans('cms.terms.preview.description'),
            'variant' => 'secondary',
        ], [
            new PageComponent('Shared:CopyField', [
                'id' => 'term-shared-preview-url',
                'label' => $this->translator->trans('cms.terms.preview.url_label'),
                'hint' => $this->translator->trans('cms.terms.preview.url_hint', [
                    '%expires%' => $formatter->format($expiresAt),
                ]),
                'value' => $previewUrl,
                'copyLabel' => $this->translator->trans('cms.terms.preview.copy'),
                'copiedMessage' => $this->translator->trans('cms.terms.preview.copied'),
                'failedMessage' => $this->translator->trans('cms.terms.preview.copy_failed'),
            ]),
            new PageComponent('Shared:ButtonLink', [
                'url' => $previewUrl,
                'label' => $this->translator->trans('cms.terms.preview.open'),
            ]),
        ]);
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published term offers it again only once it has changes to
     * publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(TermResponse $term): array
    {
        $publishable = $term->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.terms.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans(TermStatus::Published->value === $term->status ? 'cms.terms.edit.republish' : 'cms.terms.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.terms.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * When the term was created and last changed, in the editor's language.
     */
    private function dates(TermResponse $term): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, SiteTimeZone::NAME);

        return $this->translator->trans('cms.terms.edit.dates', [
            '%created%' => $formatter->format($term->createdAt),
            '%updated%' => $formatter->format($term->updatedAt),
        ]);
    }

    /**
     * The name of the term, a single line: it is also its title in search
     * results.
     */
    private function nameField(TermResponse $term): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'term-name',
                'text' => $this->translator->trans('cms.terms.name_label'),
            ]),
            new PageComponent('Shared:FormTextarea', [
                'name' => 'name',
                'id' => 'term-name',
                'value' => $term->name,
                'singleLine' => true,
                'recommendedMax' => SearchPreview::TITLE_LIMIT,
                'attributes' => [
                    'required' => true,
                    'minlength' => TermName::MIN_LENGTH,
                    'maxlength' => TermName::MAX_LENGTH,
                    'aria-describedby' => 'term-name-count',
                ],
            ]),
        ]);
    }

    private function slugField(TaxonomySegment $taxonomy, TermResponse $term): PageComponent
    {
        $affixes = $this->termAddress->affixes($taxonomy);

        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'term-slug',
                'text' => $this->translator->trans('cms.terms.edit.slug_label'),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => 'term-slug-hint',
                'text' => $this->translator->trans($taxonomy->key('edit.slug_hint')),
            ]),
            new PageComponent('Shared:FormInput', [
                'name' => 'slug',
                'id' => 'term-slug',
                'value' => $term->slug ?? '',
                'prefix' => $affixes['prefix'],
                'suffix' => $affixes['suffix'],
                // It may be left blank until the term is published.
                'attributes' => [
                    'minlength' => Slug::MIN_LENGTH,
                    'maxlength' => Slug::MAX_LENGTH,
                    'pattern' => Slug::SHAPE,
                    'autocapitalize' => 'none',
                    'spellcheck' => 'false',
                    'aria-describedby' => 'term-slug-hint',
                ],
            ]),
        ]);
    }

    /**
     * A modal that confirms an action on the term, with its texts under
     * cms.{segment}.{action}.
     */
    private function confirmModal(TaxonomySegment $taxonomy, TermResponse $term, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $action.'-term',
            'trigger' => $this->translator->trans($taxonomy->key($action.'.trigger')),
            'title' => $this->translator->trans($taxonomy->key($action.'.title')),
            'description' => $this->translator->trans($taxonomy->key($action.'.description'), ['%name%' => $term->name]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate($route, ['taxonomy' => $taxonomy->value, 'id' => $term->id]),
                'method' => 'post',
                'csrfTokenId' => $route,
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans($taxonomy->key($action.'.submit')),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }

    /**
     * How the term shows in search results, previewed above the settings
     * that shape it, so every change is seen where it lands.
     */
    private function searchEngineSection(TaxonomySegment $taxonomy, TermResponse $term): PageComponent
    {
        $address = $this->termAddress->pattern($taxonomy);

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.terms.seo.title'),
            'description' => $this->translator->trans('cms.terms.seo.description'),
            'variant' => 'disclosure',
        ], [
            new PageComponent('Shared:SearchPreview', [
                'label' => $this->translator->trans('cms.terms.seo.preview'),
                'title' => $term->name,
                'url' => str_replace('%slug%', $term->slug ?? '', $address),
                'address' => $address,
                'description' => $term->metaDescription,
                'emptyDescription' => $this->translator->trans('cms.terms.seo.preview_empty'),
                'sources' => ['title' => 'term-name', 'description' => 'term-meta-description', 'slug' => 'term-slug'],
            ]),
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'term-meta-description',
                    'text' => $this->translator->trans('cms.terms.seo.meta_description_label'),
                ]),
                new PageComponent('Shared:FormHint', [
                    'id' => 'term-meta-description-hint',
                    'text' => $this->translator->trans('cms.terms.seo.meta_description_hint'),
                ]),
                new PageComponent('Shared:FormTextarea', [
                    'name' => 'meta_description',
                    'id' => 'term-meta-description',
                    'value' => $term->metaDescription,
                    'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH,
                    'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH,
                    'attributes' => [
                        'maxlength' => Seo::DESCRIPTION_MAX_LENGTH,
                        'aria-describedby' => 'term-meta-description-hint term-meta-description-count',
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormRadioGroup', [
                'name' => 'robots',
                'id' => 'term-robots',
                'legend' => $this->translator->trans('cms.terms.seo.robots_legend'),
                'value' => $term->robots,
                'options' => array_map(fn (Robots $robots): array => [
                    'value' => $robots->value,
                    'label' => $this->translator->trans('cms.terms.seo.robots.'.$robots->value.'.label'),
                    'hint' => $this->translator->trans('cms.terms.seo.robots.'.$robots->value.'.hint'),
                ], Robots::cases()),
            ]),
        ]);
    }
}
