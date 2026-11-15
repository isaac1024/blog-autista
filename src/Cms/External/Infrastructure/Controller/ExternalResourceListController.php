<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceSearcher;
use Cms\External\Application\UnavailableResourcesCounter;
use Cms\External\Domain\ExternalResourceRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The external resources, searched by their title, all of them or those
 * no longer available, a page of them at a time.
 */
final class ExternalResourceListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ExternalResourceList $resourceList,
    ) {}

    #[Route('/externals', name: 'cms_externals', methods: ['GET'])]
    public function __invoke(
        ExternalResourceRepository $resourceRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        string $filter = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $resources = new ExternalResourceSearcher($title, ExternalResourceList::UNAVAILABLE === $filter, $page)($resourceRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->resourceList)($resources, $title, ExternalResourceList::UNAVAILABLE === $filter, $page, new UnavailableResourcesCounter()($resourceRepository)));
    }
}
