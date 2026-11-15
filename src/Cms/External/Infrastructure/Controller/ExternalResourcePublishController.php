<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourcePublisher;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Publishes an external resource as it is saved, back to its editor.
 */
final class ExternalResourcePublishController extends AbstractController
{
    #[Route('/externals/{id}/publish', name: 'cms_externals_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new ExternalResourcePublisher($id)($resourceRepository, $eventBus, $imageStorage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.external.published', 'parameters' => []]);

        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
