<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageCreateController extends AbstractController
{
    #[Route('/pages', name: 'cms_pages_create', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $title = trim($request->request->getString('title'));

        if ('' === $title) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->redirectToRoute('cms_pages', status: Response::HTTP_SEE_OTHER);
    }
}
