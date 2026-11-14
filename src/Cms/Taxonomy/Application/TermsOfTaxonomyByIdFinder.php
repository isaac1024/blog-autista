<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * Finds the terms of a taxonomy among those given, in the order given; an
 * id of no such term is left out.
 */
final readonly class TermsOfTaxonomyByIdFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private Taxonomy $taxonomy,
        private array $ids,
    ) {}

    /**
     * @return list<TermResponse>
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $terms = [];

        foreach ($this->ids as $id) {
            $term = $termRepository->find(new TermId($id));

            if (null !== $term && $term->taxonomy() === $this->taxonomy) {
                $terms[] = TermResponse::fromTerm($term);
            }
        }

        return $terms;
    }
}
