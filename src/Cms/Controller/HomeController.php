<?php

declare(strict_types=1);

namespace Cms\Controller;

use Shared\Ui\Page\Page;
use Shared\Ui\Page\PageRenderer;
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
        $page = Page::create('CMS · blog autista')
            ->withHeader('Shared:PageHeader', [
                'brand' => 'blog autista',
                'homeUrl' => $this->generateUrl('cms_home'),
                'links' => [
                    [
                        'label' => $this->translator->trans('cms.nav.logout'),
                        'url' => $this->generateUrl('cms_logout'),
                    ],
                ],
            ])
            ->withMain('Shared:PageTitle', [
                'title' => 'CMS context is up.',
            ])
            ->withFooter('Shared:PageFooter', [
                'brand' => 'blog autista',
            ]);

        return $this->pageRenderer->render($page);
    }
}
