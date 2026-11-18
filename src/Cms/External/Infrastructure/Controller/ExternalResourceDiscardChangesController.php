<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceChangesDiscarder;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Brings an external resource back to its version on the site, back to its editor.
 */
final class ExternalResourceDiscardChangesController extends AbstractController
{
    #[Route('/externals/{id}/discard', name: 'cms_externals_discard', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_discard')]
    public function __invoke(string $id, ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage): Response
    {
        try {
            new ExternalResourceChangesDiscarder($id)($resourceRepository, $eventBus, $imageStorage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.external.discarded', 'parameters' => []]);

        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
