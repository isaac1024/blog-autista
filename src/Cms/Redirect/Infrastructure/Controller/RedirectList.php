<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectsResponse;
use Cms\Redirect\Domain\Resource;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of the redirects of the site, newest first, searched by
 * their addresses, or narrowed to those that lead to a resource, and what to
 * do when there is none to list.
 */
final readonly class RedirectList
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RedirectRows $redirectRows,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(RedirectsResponse $found, string $search, ?Resource $narrowedTo, int $number): Page
    {
        // A list narrowed to a resource searches nothing; any other keeps
        // what was searched, as the search took it.
        $source = null === $narrowedTo ? trim($search) : '';
        $target = null === $narrowedTo ? '' : $narrowedTo->key();
        $page = CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.redirects.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.redirects.title'),
                'subtitle' => $this->translator->trans('cms.redirects.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_redirects_new'),
                    'label' => $this->translator->trans('cms.redirects.add'),
                ]),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_redirects'),
                'name' => 'source',
                'id' => 'redirect-search',
                'label' => $this->translator->trans('cms.redirects.search.label'),
                'submit' => $this->translator->trans('cms.redirects.search.submit'),
                'value' => $source,
            ]);

        if ([] === $found->redirects) {
            return $page->withMain('Shared:EmptyState', '' === $source ? [
                'title' => $this->translator->trans('cms.redirects.empty.title'),
                'message' => $this->translator->trans('cms.redirects.empty.message'),
            ] : [
                'title' => $this->translator->trans('cms.redirects.search.empty.title'),
                'message' => $this->translator->trans('cms.redirects.search.empty.message', ['%source%' => $source]),
            ], [
                '' === $source
                    ? new PageComponent('Shared:ButtonLink', [
                        'url' => $this->urlGenerator->generate('cms_redirects_new'),
                        'label' => $this->translator->trans('cms.redirects.add'),
                    ])
                    : new PageComponent('Shared:ButtonLink', [
                        'url' => $this->urlGenerator->generate('cms_redirects'),
                        'label' => $this->translator->trans('cms.redirects.search.empty.action'),
                    ]),
            ]);
        }

        return $page
            ->withMain('Shared:ResourceList', [
                'label' => $this->translator->trans('cms.redirects.list.label'),
            ], ($this->redirectRows)($found->redirects))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_redirects',
                array_filter(['source' => $source, 'target' => $target], static fn (string $value): bool => '' !== $value),
                $number,
                $found->hasMore,
            ));
    }
}
