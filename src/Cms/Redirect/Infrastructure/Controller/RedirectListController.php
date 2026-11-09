<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectSearcher;
use Cms\Redirect\Application\RedirectsResponse;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\DomainError;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The redirects of the site, newest first, searched by their addresses, or
 * only those that lead to a resource.
 */
final class RedirectListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly RedirectRows $redirectRows,
        private readonly ListPagination $listPagination,
    ) {}

    #[Route('/redirects', name: 'cms_redirects', methods: ['GET'])]
    public function __invoke(
        RedirectRepository $redirectRepository,
        #[MapQueryParameter]
        string $source = '',
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        string $target = '',
    ): Response {
        $source = trim($source);
        $resource = self::resource($target);

        try {
            $redirects = null === $resource
                ? new RedirectSearcher($source, $page)($redirectRepository)
                : new IncomingRedirectsFinder($resource->type(), $resource->id(), $page)($redirectRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($redirects, null === $resource ? $source : '', $target, $page));
    }

    /**
     * The resource the list is narrowed to, written as "page:<id>", if any.
     */
    private static function resource(string $target): ?Resource
    {
        [$type, $id] = [...explode(':', $target, 2), ''];
        $type = ResourceType::tryFrom($type);

        try {
            return null === $type ? null : new Resource($type, $id);
        } catch (DomainError) {
            return null;
        }
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(RedirectsResponse $found, string $source, string $target, int $number): Page
    {
        $page = CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.redirects.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.redirects.title'),
                'subtitle' => $this->translator->trans('cms.redirects.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_redirects_new'),
                    'label' => $this->translator->trans('cms.redirects.add'),
                ]),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->generateUrl('cms_redirects'),
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
                        'url' => $this->generateUrl('cms_redirects_new'),
                        'label' => $this->translator->trans('cms.redirects.add'),
                    ])
                    : new PageComponent('Shared:ButtonLink', [
                        'url' => $this->generateUrl('cms_redirects'),
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
