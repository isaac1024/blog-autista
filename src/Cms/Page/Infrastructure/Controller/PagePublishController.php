<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PagePublishController extends AbstractController
{
    #[Route('/pages/{id}/publish', name: 'cms_pages_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, PageRepository $pageRepository, EventBus $eventBus): Response
    {
        try {
            new PagePublisher($id)($pageRepository, $eventBus);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->redirectToRoute('cms_pages', status: Response::HTTP_SEE_OTHER);
    }
}
