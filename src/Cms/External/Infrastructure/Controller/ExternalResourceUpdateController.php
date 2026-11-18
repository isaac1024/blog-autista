<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceUpdater;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ExternalResourceUpdateController extends AbstractController
{
    #[Route('/externals/{id}', name: 'cms_externals_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_update')]
    public function __invoke(string $id, Request $request, ExternalResourceRepository $resourceRepository, EventBus $eventBus, Transaction $transaction, ResourceImageStorage $imageStorage): Response
    {
        $details = $request->request->all('details');

        try {
            new ExternalResourceUpdater(
                $id,
                $request->request->getString('url'),
                $request->request->getString('title'),
                $request->request->getString('summary'),
                $request->request->getString('language'),
                $details,
            )($resourceRepository, $eventBus, $transaction, $imageStorage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.external.saved', 'parameters' => []]);

        // The editor stays on the resource, to go on with it.
        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
