<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Finds the term of a taxonomy on the site at a slug.
 */
final readonly class TermFinder
{
    public function __construct(
        private string $taxonomy,
        private string $slug,
    ) {}

    public function __invoke(TermRepository $termRepository): TermResponse
    {
        $term = $termRepository->findBySlug(Taxonomy::from($this->taxonomy), $this->slug)
            ?? throw TermNotFound::withSlug($this->taxonomy, $this->slug);

        return TermResponse::fromTerm($term);
    }
}
