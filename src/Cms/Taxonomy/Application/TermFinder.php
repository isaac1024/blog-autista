<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRepository;

/**
 * Finds a term of a taxonomy by its id, failing when it doesn't exist.
 */
final readonly class TermFinder
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): TermResponse
    {
        return TermResponse::fromTerm(new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository));
    }
}
