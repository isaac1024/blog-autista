<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceSearcher;
use Cms\External\Domain\ExternalResourceRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The external resources whose title has what is searched, newest first, a
 * page of them at a time, as JSON, to pick from, such as what a text links.
 */
final class ExternalResourceSearchController extends AbstractController
{
    public function __construct(private readonly PickedExternalResource $pickedResource) {}

    #[Route('/externals/search', name: 'cms_externals_search', methods: ['GET'])]
    public function __invoke(
        ExternalResourceRepository $resourceRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        int $page = 1,
    ): JsonResponse {
        try {
            $resources = new ExternalResourceSearcher($title, false, $page)($resourceRepository);
        } catch (DomainError $exception) {
            // A page number that can't be: there is no such list.
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'externals' => array_map($this->pickedResource, $resources->resources),
            'more' => $resources->hasMore,
        ]);
    }
}
