<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The terms of a taxonomy a field holds, by their ids, for it to show their
 * current names and states. An id of no term of it is left out.
 */
final class TermLookupController extends AbstractController
{
    public function __construct(private readonly PickedTerm $pickedTerm) {}

    #[Route('/{taxonomy}/lookup', name: 'cms_terms_lookup', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT], methods: ['GET'])]
    public function __invoke(
        TaxonomySegment $taxonomy,
        TermRepository $termRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse([
            'terms' => array_map($this->pickedTerm, new TermsOfTaxonomyByIdFinder(Taxonomy::fromValue($taxonomy->taxonomy()), $ids)($termRepository)),
        ]);
    }
}
