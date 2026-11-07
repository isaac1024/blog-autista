<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageListController extends AbstractController
{
    private const string NEW_PAGE_MODAL = 'new-page';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly PageRole $pageRole,
        private readonly PageState $pageState,
    ) {}

    #[Route('/pages', name: 'cms_pages', methods: ['GET'])]
    public function __invoke(
        PageRepository $pageRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        int $page = 1,
    ): Response {
        $title = trim($title);

        try {
            $pages = new PageSearcher($title, $page)($pageRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($pages, $title));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @param list<PageResponse> $pages
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(array $pages, string $title): Page
    {
        $page = CmsLayout::page(\sprintf(
            '%s · blog autista',
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
                        'action' => $this->generateUrl('cms_pages_create'),
                        'method' => 'post',
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
                'action' => $this->generateUrl('cms_pages'),
                'name' => 'title',
                'id' => 'page-search',
                'label' => $this->translator->trans('cms.pages.search.label'),
                'submit' => $this->translator->trans('cms.pages.search.submit'),
                'value' => $title,
            ]);

        if ([] === $pages && '' === $title) {
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

        if ([] === $pages) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.pages.search.empty.title'),
                'message' => $this->translator->trans('cms.pages.search.empty.message', ['%title%' => $title]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_pages'),
                    'label' => $this->translator->trans('cms.pages.search.empty.action'),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.pages.list.label'),
        ], array_map($this->row(...), $pages));
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
            'url' => $this->generateUrl('cms_pages_edit', ['id' => $page->id]),
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
