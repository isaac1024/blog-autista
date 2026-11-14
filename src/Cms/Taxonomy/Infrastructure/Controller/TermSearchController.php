<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermSearcher;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The terms of a taxonomy whose name has every word searched, a page of them
 * at a time, as JSON, to pick from, such as the tags of a post.
 */
final class TermSearchController extends AbstractController
{
    public function __construct(private readonly PickedTerm $pickedTerm) {}

    #[Route('/{taxonomy}/search', name: 'cms_terms_search', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT], methods: ['GET'])]
    public function __invoke(
        TaxonomySegment $taxonomy,
        TermRepository $termRepository,
        #[MapQueryParameter]
        string $name = '',
        #[MapQueryParameter]
        int $page = 1,
    ): JsonResponse {
        try {
            $terms = new TermSearcher($taxonomy->taxonomy(), $name, $page)($termRepository);
        } catch (DomainError $exception) {
            // A page number that can't be: there is no such list.
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'terms' => array_map($this->pickedTerm, $terms->terms),
            'more' => $terms->hasMore,
        ]);
    }
}
