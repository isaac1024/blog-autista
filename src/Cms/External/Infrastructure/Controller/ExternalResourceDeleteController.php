<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceDeleter;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ExternalResourceUsage;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Deletes an external resource off the site that nothing uses, with its
 * images, back to the list.
 */
final class ExternalResourceDeleteController extends AbstractController
{
    public function __construct(
        private readonly EventBus $eventBus,
        private readonly ResourceImageStorage $imageStorage,
        private readonly ExternalResourceUsage $usage,
    ) {}

    #[Route('/externals/{id}/delete', name: 'cms_externals_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_delete')]
    public function __invoke(string $id, ExternalResourceRepository $resourceRepository): Response
    {
        try {
            new ExternalResourceDeleter($id)($resourceRepository, $this->eventBus, $this->imageStorage, $this->usage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.external.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_externals', status: Response::HTTP_SEE_OTHER);
    }
}
