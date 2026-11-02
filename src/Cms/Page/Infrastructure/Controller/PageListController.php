<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Shared\Ui\CmsLayout;
use Shared\Ui\Page\PageComponent;
use Shared\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/pages', name: 'cms_pages', methods: ['GET'])]
    public function __invoke(): Response
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
                    'id' => 'new-page',
                    'trigger' => $this->translator->trans('cms.pages.add'),
                    'title' => $this->translator->trans('cms.pages.modal.title'),
                    'description' => $this->translator->trans('cms.pages.modal.description'),
                ], $this->newPageForm()),
            ])
            ->withMain('Shared:EmptyState', [
                'message' => $this->translator->trans('cms.pages.empty'),
            ]);

        return $this->pageRenderer->render($page);
    }

    /**
     * @return list<PageComponent>
     */
    private function newPageForm(): array
    {
        return [
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
        ];
    }
}
