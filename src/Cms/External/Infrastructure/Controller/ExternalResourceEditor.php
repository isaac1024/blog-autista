<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\ArticleDetails;
use Cms\External\Domain\AudioDetails;
use Cms\External\Domain\DocumentDetails;
use Cms\External\Domain\Language;
use Cms\External\Domain\ResourceTitle;
use Cms\External\Domain\ResourceUrl;
use Cms\External\Domain\ResourceVersion;
use Cms\External\Domain\SocialPostDetails;
use Cms\External\Domain\StudyDetails;
use Shared\Domain\Clock;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of an external resource: what it is and where it
 * stands, its fields, those of its kind among them, its image, whether its
 * address still leads to it, and the actions that save, fill, check and
 * publish it, each offered only when the resource allows it.
 *
 * Composing a page reaches every component it is made of, so its coupling
 * grows with the page and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
final readonly class ExternalResourceEditor
{
    private const string FORM = 'external-form';

    private const string PUBLISH_FORM = 'external-publish-form';

    /**
     * How long the link to the image shown lets the editor see it.
     */
    private const string IMAGE_VALIDITY = 'P1D';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ExternalResourceState $resourceState,
        private ExternalImageUrl $imageUrl,
    ) {}

    public function __invoke(ExternalResourceResponse $resource): Page
    {
        $page = CmsLayout::page(\sprintf('%s · %s · CMS', $resource->title, $this->translator->trans('cms.externals.edit.title')))
            // What is being edited and where it stands, with what is done to it apart from editing.
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.externals.edit.title'),
                'subtitle' => \sprintf('%s · %s', $this->resourceState->kind($resource), $this->dates($resource)),
                'status' => $this->resourceState->label($resource),
            ], $this->resourceActions($resource));

        if ($resource->isPublished() && !$resource->available) {
            $page = $page->withMain('Shared:Alert', [
                'title' => $this->translator->trans('cms.externals.edit.unavailable.title'),
                'message' => $this->translator->trans('cms.externals.edit.unavailable.message'),
            ]);
        }

        return $page
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_externals_update', ['id' => $resource->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_externals_update',
                'id' => self::FORM,
            ], [
                $this->urlField($resource),
                $this->titleField($resource),
                ...(ResourceKind::SocialPost->value === $resource->kind ? [] : [$this->summaryField($resource)]),
                $this->languageField($resource),
                ...$this->detailFields($resource),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->resourceState->description($resource),
                ], $this->formActions($resource)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_externals_publish', ['id' => $resource->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_externals_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ])
            ->withMain(...$this->fillPanel($resource))
            ->withMain(...$this->availabilityPanel($resource));
    }

    /**
     * What is done to the resource apart from editing it, offered only when
     * the resource allows it: seeing it where it is, seeing what uses it,
     * and, each confirmed
     * first, taking it off the site, discarding its unpublished changes or
     * deleting it.
     *
     * @return list<PageComponent>
     */
    private function resourceActions(ExternalResourceResponse $resource): array
    {
        $actions = [
            new PageComponent('Shared:ButtonLink', [
                'url' => $resource->url,
                'label' => $this->translator->trans('cms.externals.edit.open'),
            ]),
            // What uses the resource, on a page of its own.
            new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_relations', ['type' => 'externals', 'id' => $resource->id]),
                'label' => $this->translator->trans('cms.externals.edit.relations'),
            ]),
        ];

        if ($resource->changed) {
            $actions[] = $this->confirmModal($resource, 'discard', 'cms_externals_discard', 'secondary');
        }

        if ($resource->canBeUnpublished) {
            $actions[] = $this->confirmModal($resource, 'unpublish', 'cms_externals_unpublish', 'secondary');
        }

        if ($resource->canBeDeleted) {
            $actions[] = $this->confirmModal($resource, 'delete', 'cms_externals_delete', 'danger');
        }

        return $actions;
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published resource offers it again only once it has
     * changes to publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(ExternalResourceResponse $resource): array
    {
        $publishable = $resource->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.externals.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans($resource->isPublished() ? 'cms.externals.edit.republish' : 'cms.externals.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.externals.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * When the resource was created and last changed, in the editor's language.
     */
    private function dates(ExternalResourceResponse $resource): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.externals.edit.dates', [
            '%created%' => $formatter->format($resource->createdAt),
            '%updated%' => $formatter->format($resource->updatedAt),
        ]);
    }

    /**
     * Where the resource is: it only changes to another address of the same
     * kind, by the same provider.
     */
    private function urlField(ExternalResourceResponse $resource): PageComponent
    {
        return $this->field('url', 'cms.externals.url_label', 'cms.externals.edit.url_hint', new PageComponent('Shared:FormInput', [
            'name' => 'url',
            'id' => 'external-url',
            'type' => 'url',
            'value' => $resource->url,
            'attributes' => [
                'required' => true,
                'maxlength' => ResourceUrl::MAX_LENGTH,
                'spellcheck' => 'false',
                'aria-describedby' => 'external-url-hint',
            ],
        ]));
    }

    private function titleField(ExternalResourceResponse $resource): PageComponent
    {
        return $this->field('title', 'cms.externals.edit.title_label', 'cms.externals.edit.title_hint', new PageComponent('Shared:FormTextarea', [
            'name' => 'title',
            'id' => 'external-title',
            'value' => $resource->title,
            'singleLine' => true,
            'attributes' => [
                'required' => true,
                'minlength' => ResourceTitle::MIN_LENGTH,
                'maxlength' => ResourceTitle::MAX_LENGTH,
                'aria-describedby' => 'external-title-hint',
            ],
        ]));
    }

    private function summaryField(ExternalResourceResponse $resource): PageComponent
    {
        return $this->field('summary', 'cms.externals.edit.summary_label', 'cms.externals.edit.summary_hint', new PageComponent('Shared:FormTextarea', [
            'name' => 'summary',
            'id' => 'external-summary',
            'value' => $resource->summary ?? '',
            'attributes' => [
                'maxlength' => ResourceVersion::SUMMARY_MAX_LENGTH,
                'aria-describedby' => 'external-summary-hint',
            ],
        ]));
    }

    private function languageField(ExternalResourceResponse $resource): PageComponent
    {
        return $this->field('language', 'cms.externals.edit.language_label', 'cms.externals.edit.language_hint', new PageComponent('Shared:FormSelect', [
            'name' => 'language',
            'id' => 'external-language',
            'value' => $resource->language,
            // A resource always has one, and the list, with no option for
            // none, always sends one: it needs no "required".
            'none' => null,
            'options' => array_map(fn (Language $language): array => [
                'value' => $language->value,
                'label' => $this->translator->trans('cms.externals.language.'.$language->value),
            ], Language::cases()),
            'attributes' => ['aria-describedby' => 'external-language-hint'],
        ]));
    }

    /**
     * The details of the kind of the resource, each in a field of its own.
     *
     * @return list<PageComponent>
     */
    private function detailFields(ExternalResourceResponse $resource): array
    {
        return match (ResourceKind::from($resource->kind)) {
            ResourceKind::Video => [],
            ResourceKind::SocialPost => [
                $this->textDetail($resource, 'author', SocialPostDetails::AUTHOR_MAX_LENGTH),
                $this->textDetail($resource, 'handle', SocialPostDetails::HANDLE_MAX_LENGTH),
                $this->dateDetail($resource, 'posted_on'),
                $this->linesDetail($resource, 'text', SocialPostDetails::TEXT_MAX_LENGTH),
                $this->flagDetail($resource, 'with_media'),
            ],
            ResourceKind::Audio => [$this->textDetail($resource, 'show', AudioDetails::SHOW_MAX_LENGTH)],
            ResourceKind::Article => [
                $this->textDetail($resource, 'publisher', ArticleDetails::PUBLISHER_MAX_LENGTH),
                $this->dateDetail($resource, 'published_on'),
            ],
            ResourceKind::Study => [
                $this->textDetail($resource, 'authors', StudyDetails::AUTHORS_MAX_LENGTH),
                $this->textDetail($resource, 'journal', StudyDetails::JOURNAL_MAX_LENGTH),
                $this->numberDetail($resource, 'year', StudyDetails::MIN_YEAR, StudyDetails::MAX_YEAR),
            ],
            ResourceKind::Document => [
                $this->textDetail($resource, 'publisher', DocumentDetails::PUBLISHER_MAX_LENGTH),
                $this->numberDetail($resource, 'bytes', 0, null),
                $this->numberDetail($resource, 'pages', 1, null),
            ],
        };
    }

    private function textDetail(ExternalResourceResponse $resource, string $detail, int $maxLength): PageComponent
    {
        return $this->detailField($detail, new PageComponent('Shared:FormTextarea', [
            'name' => \sprintf('details[%s]', $detail),
            'id' => 'external-'.$detail,
            'value' => (string) ($resource->details[$detail] ?? ''),
            'singleLine' => true,
            'attributes' => ['maxlength' => $maxLength, 'aria-describedby' => \sprintf('external-%s-hint', $detail)],
        ]));
    }

    private function linesDetail(ExternalResourceResponse $resource, string $detail, int $maxLength): PageComponent
    {
        return $this->detailField($detail, new PageComponent('Shared:FormTextarea', [
            'name' => \sprintf('details[%s]', $detail),
            'id' => 'external-'.$detail,
            'value' => (string) ($resource->details[$detail] ?? ''),
            'rows' => 8,
            'attributes' => ['maxlength' => $maxLength, 'aria-describedby' => \sprintf('external-%s-hint', $detail)],
        ]));
    }

    private function dateDetail(ExternalResourceResponse $resource, string $detail): PageComponent
    {
        return $this->detailField($detail, new PageComponent('Shared:FormInput', [
            'name' => \sprintf('details[%s]', $detail),
            'id' => 'external-'.$detail,
            'type' => 'date',
            'value' => (string) ($resource->details[$detail] ?? ''),
            'attributes' => ['max' => Clock::now()->format('Y-m-d'), 'aria-describedby' => \sprintf('external-%s-hint', $detail)],
        ]));
    }

    private function numberDetail(ExternalResourceResponse $resource, string $detail, int $min, ?int $max): PageComponent
    {
        return $this->detailField($detail, new PageComponent('Shared:FormInput', [
            'name' => \sprintf('details[%s]', $detail),
            'id' => 'external-'.$detail,
            'type' => 'number',
            'value' => (string) ($resource->details[$detail] ?? ''),
            'attributes' => array_filter([
                'min' => $min,
                'max' => $max,
                'step' => 1,
                'inputmode' => 'numeric',
                'aria-describedby' => \sprintf('external-%s-hint', $detail),
            ], static fn (int|string|null $value): bool => null !== $value),
        ]));
    }

    /**
     * A yes or no, as a pair of choices, so it is always sent.
     */
    private function flagDetail(ExternalResourceResponse $resource, string $detail): PageComponent
    {
        return new PageComponent('Shared:FormRadioGroup', [
            'name' => \sprintf('details[%s]', $detail),
            'id' => 'external-'.$detail,
            'legend' => $this->translator->trans(\sprintf('cms.externals.details.%s.label', $detail)),
            'value' => true === ($resource->details[$detail] ?? false) ? '1' : '0',
            'options' => [
                ['value' => '1', 'label' => $this->translator->trans(\sprintf('cms.externals.details.%s.yes', $detail)), 'hint' => ''],
                ['value' => '0', 'label' => $this->translator->trans(\sprintf('cms.externals.details.%s.no', $detail)), 'hint' => ''],
            ],
        ]);
    }

    private function detailField(string $detail, PageComponent $input): PageComponent
    {
        return $this->field($detail, \sprintf('cms.externals.details.%s.label', $detail), \sprintf('cms.externals.details.%s.hint', $detail), $input);
    }

    /**
     * A field with its label and its hint, whose ids follow its name.
     */
    private function field(string $name, string $label, string $hint, PageComponent $input): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'external-'.$name,
                'text' => $this->translator->trans($label),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => \sprintf('external-%s-hint', $name),
                'text' => $this->translator->trans($hint),
            ]),
            $input,
        ]);
    }

    /**
     * The image read from the resource, if it has one, and a way to read it
     * all again from its saved address: the image is never chosen by hand.
     *
     * @return array{string, array<string, mixed>, list<PageComponent>}
     */
    private function fillPanel(ExternalResourceResponse $resource): array
    {
        $children = [];
        $shape = null === $resource->imageShape ? null : ImageShape::from($resource->imageShape);

        if (null !== $shape) {
            $children[] = null === $resource->image
                ? new PageComponent('Shared:Notice', ['message' => $this->translator->trans('cms.externals.fill.no_image')])
                : new PageComponent('Shared:ImagePreview', [
                    'src' => ($this->imageUrl)($resource->id, $resource->image, Clock::now()->add(new \DateInterval(self::IMAGE_VALIDITY))),
                    'alt' => $this->translator->trans('cms.externals.fill.image_alt', ['%title%' => $resource->title]),
                    'width' => $shape->width(),
                    'height' => $shape->height(),
                ]);
        }

        $children[] = new PageComponent('Shared:Form', [
            'action' => $this->urlGenerator->generate('cms_externals_fill', ['id' => $resource->id]),
            'method' => 'post',
            'csrfTokenId' => 'cms_externals_fill',
        ], [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.externals.fill.submit'),
                'variant' => 'secondary',
            ]),
        ]);

        return ['Shared:Panel', [
            'title' => $this->translator->trans('cms.externals.fill.title'),
            'description' => $this->translator->trans(null === $shape ? 'cms.externals.fill.description' : 'cms.externals.fill.description_image'),
        ], $children];
    }

    /**
     * Whether the address on the site still leads to the resource, as it was
     * last checked, and a way to check it now.
     *
     * @return array{string, array<string, mixed>, list<PageComponent>}
     */
    private function availabilityPanel(ExternalResourceResponse $resource): array
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);

        return ['Shared:Panel', [
            'title' => $this->translator->trans('cms.externals.check.title'),
            'description' => $this->translator->trans($resource->available ? 'cms.externals.check.available' : 'cms.externals.check.unavailable', [
                '%url%' => $resource->checkedUrl,
                '%date%' => null === $resource->checkedAt ? $this->translator->trans('cms.externals.check.never') : $formatter->format($resource->checkedAt),
            ]),
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_externals_check', ['id' => $resource->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_externals_check',
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.externals.check.submit'),
                    'variant' => 'secondary',
                ]),
            ]),
        ]];
    }

    /**
     * A modal that confirms an action on the resource, with its texts under
     * cms.externals.{action}.
     */
    private function confirmModal(ExternalResourceResponse $resource, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $action.'-external',
            'trigger' => $this->translator->trans(\sprintf('cms.externals.%s.trigger', $action)),
            'title' => $this->translator->trans(\sprintf('cms.externals.%s.title', $action)),
            'description' => $this->translator->trans(\sprintf('cms.externals.%s.description', $action), ['%title%' => $resource->title]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate($route, ['id' => $resource->id]),
                'method' => 'post',
                'csrfTokenId' => $route,
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans(\sprintf('cms.externals.%s.submit', $action)),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }
}
