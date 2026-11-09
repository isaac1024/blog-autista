<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PagePublishController extends AbstractController
{
    #[Route('/pages/{id}/publish', name: 'cms_pages_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository, EventBus $eventBus, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new PagePublisher($id)($pageRepository, $eventBus);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.page.published', 'parameters' => []]);

        // The editor stays on the page, to go on with it.
        return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
