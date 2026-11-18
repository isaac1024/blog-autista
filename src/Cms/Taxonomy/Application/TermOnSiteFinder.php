<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * Finds a term of a taxonomy as it is on the site; none while it is off it,
 * or when there is no such term.
 */
final readonly class TermOnSiteFinder
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

    public function __invoke(TermRepository $termRepository): ?TermOnSiteResponse
    {
        $term = $termRepository->find(new TermId($this->id));

        return null !== $term && $term->taxonomy() === $this->taxonomy ? TermOnSiteResponse::fromTerm($term) : null;
    }
}
