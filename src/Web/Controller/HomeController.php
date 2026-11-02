<?php

declare(strict_types=1);

namespace Web\Controller;

use Shared\Ui\Page\Page;
use Shared\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private readonly PageRenderer $pageRenderer) {}

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $page = Page::create('Web · blog autista')
            ->withHeader('Shared:PageHeader', [
                'brand' => 'blog autista',
                'homeUrl' => $this->generateUrl('web_home'),
            ])
            ->withMain('Shared:PageTitle', [
                'title' => 'Web context is up.',
            ])
            ->withFooter('Shared:PageFooter', [
                'brand' => 'blog autista',
            ]);

        return $this->pageRenderer->render($page);
    }
}
