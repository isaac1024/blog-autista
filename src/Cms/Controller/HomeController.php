<?php

declare(strict_types=1);

namespace Cms\Controller;

use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/', name: 'cms_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->pageRenderer->render($this->page());
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(): Page
    {
        return CmsLayout::page(\sprintf('%s · blog autista', $this->translator->trans('cms.home.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.home.title'),
                'subtitle' => $this->translator->trans('cms.home.subtitle'),
            ])
            // One entry point per section of the CMS, so the next step is always in sight.
            ->withMain('Shared:Panel', [
                'title' => $this->translator->trans('cms.home.pages.title'),
                'description' => $this->translator->trans('cms.home.pages.description'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_pages'),
                    'label' => $this->translator->trans('cms.home.pages.action'),
                ]),
            ]);
    }
}
