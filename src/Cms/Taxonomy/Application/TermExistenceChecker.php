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
    private Taxonomy $taxonomy;

    /**
     * @param string $taxonomy one of the Taxonomy values, such as TermResponse::SECTION
     */
    public function __construct(
        string $taxonomy,
        private string $id,
    ) {
        $this->taxonomy = Taxonomy::fromValue($taxonomy);
    }

    public function __invoke(TermRepository $termRepository): bool
    {
        return $termRepository->find(new TermId($this->id))?->taxonomy() === $this->taxonomy;
    }
}
