<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageDeleter;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageUsage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PageDeleteController extends AbstractController
{
    #[Route('/pages/{id}/delete', name: 'cms_pages_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, PageRepository $pageRepository, EventBus $eventBus, PageUsage $pageUsage): Response
    {
        try {
            new PageDeleter($id)($pageRepository, $eventBus, $pageUsage);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('cms_pages', status: Response::HTTP_SEE_OTHER);
    }
}
