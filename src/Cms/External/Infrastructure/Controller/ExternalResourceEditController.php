<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Shared\Infrastructure\Ui\KeptEdit;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The editor of an external resource.
 */
final class ExternalResourceEditController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ExternalResourceEditor $resourceEditor,
        private readonly KeptEdit $keptEdit,
    ) {}

    #[Route('/externals/{id}', name: 'cms_externals_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ExternalResourceRepository $resourceRepository, Request $request): Response
    {
        try {
            $resource = new ExternalResourceFinder($id)($resourceRepository);
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(KeptEdit::onto(($this->resourceEditor)($resource), $this->keptEdit->taken($request)));
    }
}
