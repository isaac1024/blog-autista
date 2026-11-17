<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesResponse;
use Cms\Shared\Infrastructure\Ui\PageRole;
use Cms\Shared\Infrastructure\Ui\PageState;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of pages, newest first, searched by their title, with
 * a way to create one, and what to do when there is none to list.
 */
final readonly class PageList
{
    private const string NEW_PAGE_MODAL = 'new-page';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private PageRole $pageRole,
        private PageState $pageState,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(PagesResponse $found, string $search, int $number): Page
    {
        // The search box keeps what was searched, as the search took it.
        $title = trim($search);
        $page = CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans('cms.pages.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.pages.title'),
                'subtitle' => $this->translator->trans('cms.pages.subtitle'),
            ], [
                new PageComponent('Shared:Modal', [
                    'id' => self::NEW_PAGE_MODAL,
                    'trigger' => $this->translator->trans('cms.pages.add'),
                    'title' => $this->translator->trans('cms.pages.modal.title'),
                    'description' => $this->translator->trans('cms.pages.modal.description'),
                ], [
                    new PageComponent('Shared:Form', [
                        'action' => $this->urlGenerator->generate('cms_pages_create'),
                        'method' => 'post',
                        'csrfTokenId' => 'cms_pages_create',
                    ], [
                        new PageComponent('Shared:FormField', [], [
                            new PageComponent('Shared:FormLabel', [
                                'for' => 'page-title',
                                'text' => $this->translator->trans('cms.pages.modal.title_label'),
                            ]),
                            new PageComponent('Shared:FormInput', [
                                'name' => 'title',
                                'id' => 'page-title',
                                'attributes' => [
                                    'required' => true,
                                    'autofocus' => true,
                                ],
                            ]),
                        ]),
                        new PageComponent('Shared:FormButton', [
                            'label' => $this->translator->trans('cms.pages.modal.submit'),
                        ]),
                    ]),
                ]),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_pages'),
                'name' => 'title',
                'id' => 'page-search',
                'label' => $this->translator->trans('cms.pages.search.label'),
                'submit' => $this->translator->trans('cms.pages.search.submit'),
                'value' => $title,
            ]);

        if ([] === $found->pages && '' === $title) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.pages.empty.title'),
                'message' => $this->translator->trans('cms.pages.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::NEW_PAGE_MODAL,
                    'label' => $this->translator->trans('cms.pages.empty.action'),
                ]),
            ]);
        }

        if ([] === $found->pages) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.pages.search.empty.title'),
                'message' => $this->translator->trans('cms.pages.search.empty.message', ['%title%' => $title]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_pages'),
                    'label' => $this->translator->trans('cms.pages.search.empty.action'),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.pages.list.label'),
        ], array_map($this->row(...), $found->pages))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_pages',
                '' === $title ? [] : ['title' => $title],
                $number,
                $found->hasMore,
            ));
    }

    /**
     * A page with its address, when it last changed, where it stands, and the
     * role of the unique ones, so editors can tell the home or the legal pages
     * apart from the rest. A published page shows the address it has on the
     * site, which changes only when it is published again.
     */
    private function row(PageResponse $page): PageComponent
    {
        return new PageComponent('Shared:ResourceRow', [
            'title' => $page->title,
            'url' => $this->urlGenerator->generate('cms_pages_edit', ['id' => $page->id]),
            'meta' => \sprintf('%s · %s', $this->address($page), $this->pageState->lastUpdate($page)),
            'badge' => ($this->pageRole)($page),
            'status' => $this->pageState->label($page),
        ]);
    }

    private function address(PageResponse $page): string
    {
        if ($page->home) {
            return '/';
        }

        $slug = $page->publishedSlug ?? $page->slug;

        return null === $slug ? $this->translator->trans('cms.pages.list.no_slug') : \sprintf('/%s.html', $slug);
    }
}
