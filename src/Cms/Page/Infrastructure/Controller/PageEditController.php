<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Shared\Ui\CmsLayout;
use Shared\Ui\Page\Page;
use Shared\Ui\Page\PageComponent;
use Shared\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageEditController extends AbstractController
{
    private const string DELETE_PAGE_MODAL = 'delete-page';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/pages/{id}', name: 'cms_pages_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($page));
    }

    private function page(PageResponse $page): Page
    {
        return CmsLayout::page(\sprintf(
            '%s · blog autista',
            $this->translator->trans('cms.pages.edit.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.pages.edit.title'),
                'subtitle' => $this->translator->trans('cms.pages.edit.subtitle'),
            ], [
                new PageComponent('Shared:Modal', [
                    'id' => self::DELETE_PAGE_MODAL,
                    'trigger' => $this->translator->trans('cms.pages.delete.trigger'),
                    'title' => $this->translator->trans('cms.pages.delete.title'),
                    'description' => $this->translator->trans('cms.pages.delete.description', ['%title%' => $page->title]),
                    'variant' => 'danger',
                ], [
                    new PageComponent('Shared:Form', [
                        'action' => $this->generateUrl('cms_pages_delete', ['id' => $page->id]),
                        'method' => 'post',
                    ], [
                        new PageComponent('Shared:FormButton', [
                            'label' => $this->translator->trans('cms.pages.delete.submit'),
                            'variant' => 'danger',
                        ]),
                    ]),
                ]),
            ])
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_update', ['id' => $page->id]),
                'method' => 'post',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'page-title',
                        'text' => $this->translator->trans('cms.pages.edit.title_label'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'title',
                        'id' => 'page-title',
                        'value' => $page->title,
                        'attributes' => [
                            'required' => true,
                            'minlength' => Title::MIN_LENGTH,
                            'maxlength' => Title::MAX_LENGTH,
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'page-slug',
                        'text' => $this->translator->trans('cms.pages.edit.slug_label'),
                    ]),
                    new PageComponent('Shared:FormHint', [
                        'id' => 'page-slug-hint',
                        'text' => $this->translator->trans('cms.pages.edit.slug_hint'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'slug',
                        'id' => 'page-slug',
                        'value' => $page->slug,
                        'attributes' => [
                            'required' => true,
                            'minlength' => Slug::MIN_LENGTH,
                            'maxlength' => Slug::MAX_LENGTH,
                            'pattern' => Slug::SHAPE,
                            'autocapitalize' => 'none',
                            'spellcheck' => 'false',
                            'aria-describedby' => 'page-slug-hint',
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.pages.edit.submit'),
                ]),
            ]);
    }
}
