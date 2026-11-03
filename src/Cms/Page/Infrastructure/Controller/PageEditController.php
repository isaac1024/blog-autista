<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

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
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/pages/{id}', name: 'cms_pages_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        return $this->pageRenderer->render($this->page($id));
    }

    private function page(string $id): Page
    {
        return CmsLayout::page(\sprintf(
            '%s · blog autista',
            $this->translator->trans('cms.pages.edit.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.pages.edit.title'),
                'subtitle' => $this->translator->trans('cms.pages.edit.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_pages'),
                    'label' => $this->translator->trans('cms.pages.edit.back'),
                ]),
            ])
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_edit', ['id' => $id]),
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
