<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageChangesDiscarder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Clock;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Goes back to the editor, which shows the page as it is on the site again.
 */
final class PageDiscardChangesController extends AbstractController
{
    #[Route('/pages/{id}/discard', name: 'cms_pages_discard', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, PageRepository $pageRepository, Clock $clock): Response
    {
        try {
            new PageChangesDiscarder($id)($pageRepository, $clock);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
