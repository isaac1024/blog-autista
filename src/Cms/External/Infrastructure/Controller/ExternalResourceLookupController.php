<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourcesFinder;
use Cms\External\Domain\ExternalResourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The external resources a field holds, by their ids, for it to show their
 * current titles and states. An id of no resource is left out.
 */
final class ExternalResourceLookupController extends AbstractController
{
    public function __construct(private readonly PickedExternalResource $pickedResource) {}

    #[Route('/externals/lookup', name: 'cms_externals_lookup', methods: ['GET'])]
    public function __invoke(
        ExternalResourceRepository $resourceRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['externals' => array_map($this->pickedResource, new ExternalResourcesFinder($ids)($resourceRepository))]);
    }
}
