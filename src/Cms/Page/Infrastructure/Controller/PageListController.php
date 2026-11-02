<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Shared\Ui\CmsLayout;
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
                'subtitle' => $this->translator->trans('cms.pages.empty'),
            ]);

        return $this->pageRenderer->render($page);
    }
}
