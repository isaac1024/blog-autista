<?php

declare(strict_types=1);

namespace Cms\Controller;

use Shared\Ui\CmsLayout;
use Shared\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private readonly PageRenderer $pageRenderer) {}

    #[Route('/', name: 'cms_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $page = CmsLayout::page('CMS · blog autista')
            ->withMain('Shared:PageTitle', [
                'title' => 'CMS context is up.',
            ]);

        return $this->pageRenderer->render($page);
    }
}
