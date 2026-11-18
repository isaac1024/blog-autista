<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceCreator;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Creates an external resource from its address, read there, and goes on
 * to its editor; an address another resource has leads to that one.
 */
final class ExternalResourceCreateController extends AbstractController
{
    public function __construct(
        private readonly EventBus $eventBus,
        private readonly ResourceReader $reader,
        private readonly ResourceImageFetcher $imageFetcher,
        private readonly ResourceImageStorage $imageStorage,
        #[Autowire(env: 'WEB_HOST')]
        private readonly string $webHost,
    ) {}

    #[Route('/externals', name: 'cms_externals_create', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_create')]
    public function __invoke(Request $request, ExternalResourceRepository $resourceRepository): Response
    {
        $id = ExternalResourceId::random()->value();

        try {
            $read = new ExternalResourceCreator($id, $request->request->getString('url'), $this->webHost)($resourceRepository, $this->eventBus, $this->reader, $this->imageFetcher, $this->imageStorage);
        } catch (DomainError $error) {
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $holder = $error->parameters()['%id%'] ?? '';

            // The resource with that address is the one to go on with.
            if ('errors.external.url.taken' === $error->key() && \is_string($holder) && '' !== $holder) {
                return $this->redirectToRoute('cms_externals_edit', ['id' => $holder], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('cms_externals', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash($read ? 'success' : 'error', ['key' => $read ? 'cms.notices.external.created' : 'cms.notices.external.created_unread', 'parameters' => []]);

        // The editor goes on with the resource just created.
        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
