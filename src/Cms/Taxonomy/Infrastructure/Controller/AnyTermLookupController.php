<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The sections and tags a text links to, by their ids, whatever their
 * taxonomy, for its editor to show their current names and states. An id of
 * no term is left out.
 */
final class AnyTermLookupController extends AbstractController
{
    public function __construct(private readonly PickedTerm $pickedTerm) {}

    #[Route('/terms/lookup', name: 'cms_any_terms_lookup', methods: ['GET'])]
    public function __invoke(
        TermRepository $termRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['terms' => array_map($this->pickedTerm, new TermsFinder($ids)($termRepository))]);
    }
}
