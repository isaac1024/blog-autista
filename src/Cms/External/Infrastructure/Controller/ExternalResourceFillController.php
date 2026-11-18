<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceFiller;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Fills an external resource from its saved address anew, back to its
 * editor.
 */
final class ExternalResourceFillController extends AbstractController
{
    public function __construct(
        private readonly EventBus $eventBus,
        private readonly Transaction $transaction,
        private readonly ResourceReader $reader,
        private readonly ResourceImageFetcher $imageFetcher,
        private readonly ResourceImageStorage $imageStorage,
    ) {}

    #[Route('/externals/{id}/fill', name: 'cms_externals_fill', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_fill')]
    public function __invoke(string $id, ExternalResourceRepository $resourceRepository): Response
    {
        try {
            $read = new ExternalResourceFiller($id)($resourceRepository, $this->eventBus, $this->transaction, $this->reader, $this->imageFetcher, $this->imageStorage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash($read ? 'success' : 'error', ['key' => $read ? 'cms.notices.external.filled' : 'cms.notices.external.unread', 'parameters' => []]);

        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
