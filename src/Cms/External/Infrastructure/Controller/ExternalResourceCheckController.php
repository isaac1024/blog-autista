<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceChecker;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\CheckResult;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Checks whether the address of an external resource still leads to it,
 * and tells what that found on its editor: available, unavailable, moved
 * elsewhere, or nothing sure, which changes nothing.
 */
final class ExternalResourceCheckController extends AbstractController
{
    public function __construct(
        private readonly EventBus $eventBus,
        private readonly ResourceReader $reader,
        private readonly ResourceImageStorage $imageStorage,
    ) {}

    #[Route('/externals/{id}/check', name: 'cms_externals_check', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_externals_check')]
    public function __invoke(string $id, ExternalResourceRepository $resourceRepository): Response
    {
        try {
            $outcome = new ExternalResourceChecker($id)($resourceRepository, $this->eventBus, $this->reader, $this->imageStorage);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $sure = \in_array($outcome->result, [CheckResult::Available, CheckResult::Unavailable], true);
        $this->addFlash($sure ? 'success' : 'error', [
            'key' => 'cms.notices.external.checked.'.$outcome->result->value,
            'parameters' => ['%status%' => $outcome->status ?? '—', '%url%' => $outcome->movedTo ?? ''],
        ]);

        return $this->redirectToRoute('cms_externals_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
