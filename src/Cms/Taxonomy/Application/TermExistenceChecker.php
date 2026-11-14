<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * Tells whether there is a term of a taxonomy with the id, on the site or not.
 */
final readonly class TermExistenceChecker
{
    public function __construct(
        private Taxonomy $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): bool
    {
        return $termRepository->find(new TermId($this->id))?->taxonomy() === $this->taxonomy;
    }
}
