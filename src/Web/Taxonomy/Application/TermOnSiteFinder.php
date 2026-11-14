<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Finds a term of a taxonomy on the site by its id; none while it is off it.
 */
final readonly class TermOnSiteFinder
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): ?TermResponse
    {
        $term = $termRepository->find(new TermId($this->id));

        return null !== $term && $term->taxonomy() === Taxonomy::from($this->taxonomy) ? TermResponse::fromTerm($term) : null;
    }
}
