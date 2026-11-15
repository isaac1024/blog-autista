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
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Fills an external resource from its saved address anew, back to its
 * editor.
 */
final class ExternalResourceFillController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly EventBus $eventBus,
        private readonly ResourceReader $reader,
        private readonly ResourceImageFetcher $imageFetcher,
        private readonly ResourceImageStorage $imageStorage,
    ) {}

    #[Route('/externals/{id}/fill', name: 'cms_externals_fill', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, ExternalResourceRepository $resourceRepository): Response
    {
        $this->csrf->assertValid($request);

        try {
            $read = new ExternalResourceFiller($id)($resourceRepository, $this->eventBus, $this->reader, $this->imageFetcher, $this->imageStorage);
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
