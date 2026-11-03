<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageUpdater;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PageUpdateController extends AbstractController
{
    #[Route('/pages/{id}', name: 'cms_pages_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository): Response
    {
        try {
            new PageUpdater($id, $request->request->getString('title'), $request->request->getString('slug'))($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->redirectToRoute('cms_pages', status: Response::HTTP_SEE_OTHER);
    }
}
