<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * Loads a term by its id, as a term of the taxonomy given: one of another
 * taxonomy is as missing as one that doesn't exist, so a section is never
 * edited as a tag.
 */
final readonly class TermOfTaxonomy
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): Term
    {
        $taxonomy = Taxonomy::fromValue($this->taxonomy);
        $term = $termRepository->find(new TermId($this->id));

        if (null === $term || $term->taxonomy() !== $taxonomy) {
            throw TermNotFound::withId($this->taxonomy, $this->id);
        }

        return $term;
    }
}
