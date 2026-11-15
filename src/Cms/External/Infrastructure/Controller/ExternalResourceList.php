<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Application\ExternalResourcesResponse;
use Cms\External\Domain\ResourceUrl;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of external resources, newest first, searched by their
 * title, all of them or only those no longer available, with a way to add
 * one from its address, and what to do when there is none to list.
 */
final readonly class ExternalResourceList
{
    /**
     * The filter that lists only the resources no longer available.
     */
    public const string UNAVAILABLE = 'unavailable';
    private const string NEW_RESOURCE_MODAL = 'new-external';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ExternalResourceState $resourceState,
        private ListPagination $listPagination,
    ) {}

    /**
     * @param int $unavailable how many resources on the site are unavailable
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function __invoke(ExternalResourcesResponse $found, string $search, bool $unavailableOnly, int $number, int $unavailable): Page
    {
        // The search box keeps what was searched, as the search took it.
        $title = trim($search);
        $page = $this->head($title, $unavailableOnly, $unavailable);

        if ([] === $found->resources) {
            return $page->withMain(...$this->emptyState($title, $unavailableOnly));
        }

        $query = array_filter(['title' => $title, 'filter' => $unavailableOnly ? self::UNAVAILABLE : ''], static fn (string $value): bool => '' !== $value);

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.externals.list.label'),
        ], array_map($this->row(...), $found->resources))
            ->withMain('Shared:Pagination', ($this->listPagination)('cms_externals', $query, $number, $found->hasMore));
    }

    /**
     * The title of the page, with a way to add a resource; an alert when
     * resources on the site are unavailable; and how to narrow the list.
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    private function head(string $title, bool $unavailableOnly, int $unavailable): Page
    {
        $page = CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.externals.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.externals.title'),
                'subtitle' => $this->translator->trans('cms.externals.subtitle'),
            ], [
                $this->newResourceModal(),
            ]);

        // Resources the site no longer paints, as it found when checking them.
        if (0 < $unavailable && !$unavailableOnly) {
            $page = $page->withMain('Shared:Alert', [
                'title' => $this->translator->trans('cms.externals.unavailable_alert.title'),
                'message' => $this->translator->trans('cms.externals.unavailable_alert.message', ['%count%' => $unavailable]),
            ]);
        }

        $searched = '' === $title ? [] : ['title' => $title];
        $filtered = $unavailableOnly ? ['filter' => self::UNAVAILABLE] : [];

        return $page
            ->withMain('Shared:SegmentedNav', [
                'label' => $this->translator->trans('cms.externals.filter.label'),
                'links' => [
                    ['label' => $this->translator->trans('cms.externals.filter.all'), 'url' => $this->urlGenerator->generate('cms_externals', $searched), 'current' => !$unavailableOnly],
                    ['label' => $this->translator->trans('cms.externals.filter.unavailable', ['%count%' => $unavailable]), 'url' => $this->urlGenerator->generate('cms_externals', ['filter' => self::UNAVAILABLE] + $searched), 'current' => $unavailableOnly],
                ],
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_externals', $filtered),
                'name' => 'title',
                'id' => 'external-search',
                'label' => $this->translator->trans('cms.externals.search.label'),
                'submit' => $this->translator->trans('cms.externals.search.submit'),
                'value' => $title,
            ]);
    }

    /**
     * What to do when nothing is listed: add the first resource when there
     * is none, or see them all again when a search or the filter left none.
     *
     * @return array{string, array<string, string>, list<PageComponent>}
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    private function emptyState(string $title, bool $unavailableOnly): array
    {
        if ('' === $title && !$unavailableOnly) {
            return ['Shared:EmptyState', [
                'title' => $this->translator->trans('cms.externals.empty.title'),
                'message' => $this->translator->trans('cms.externals.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::NEW_RESOURCE_MODAL,
                    'label' => $this->translator->trans('cms.externals.empty.action'),
                ]),
            ]];
        }

        return ['Shared:EmptyState', [
            'title' => $this->translator->trans('cms.externals.search.empty.title'),
            'message' => $this->translator->trans('' === $title ? 'cms.externals.search.empty.unavailable' : 'cms.externals.search.empty.message', ['%title%' => $title]),
        ], [
            new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_externals'),
                'label' => $this->translator->trans('cms.externals.search.empty.action'),
            ]),
        ]];
    }

    /**
     * A resource with what it is, its host, when it last changed and where
     * it stands.
     */
    private function row(ExternalResourceResponse $resource): PageComponent
    {
        return new PageComponent('Shared:ResourceRow', [
            'title' => $resource->title,
            'url' => $this->urlGenerator->generate('cms_externals_edit', ['id' => $resource->id]),
            'meta' => \sprintf(
                '%s · %s · %s',
                $this->resourceState->kind($resource),
                new ResourceUrl($resource->url)->address()->host(),
                $this->resourceState->lastUpdate($resource),
            ),
            'status' => $this->resourceState->label($resource),
        ]);
    }

    /**
     * The address of the new resource, which tells what it is and fills it.
     */
    private function newResourceModal(): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::NEW_RESOURCE_MODAL,
            'trigger' => $this->translator->trans('cms.externals.add'),
            'title' => $this->translator->trans('cms.externals.modal.title'),
            'description' => $this->translator->trans('cms.externals.modal.description'),
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_externals_create'),
                'method' => 'post',
                'csrfTokenId' => 'cms_externals_create',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'external-new-url',
                        'text' => $this->translator->trans('cms.externals.url_label'),
                    ]),
                    new PageComponent('Shared:FormHint', [
                        'id' => 'external-new-url-hint',
                        'text' => $this->translator->trans('cms.externals.modal.url_hint'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'url',
                        'id' => 'external-new-url',
                        'type' => 'url',
                        'attributes' => [
                            'required' => true,
                            'maxlength' => ResourceUrl::MAX_LENGTH,
                            'autofocus' => true,
                            'spellcheck' => 'false',
                            'aria-describedby' => 'external-new-url-hint',
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.externals.modal.submit'),
                ]),
            ]),
        ]);
    }
}
