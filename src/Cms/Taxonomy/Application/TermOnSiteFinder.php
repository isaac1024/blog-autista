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
    public function __construct(
        private Taxonomy $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): ?TermOnSiteResponse
    {
        $term = $termRepository->find(new TermId($this->id));
        $version = $term?->taxonomy() === $this->taxonomy ? $term->onSite() : null;
        $slug = $version?->slug();

        return null === $version || null === $slug
            ? null
            : new TermOnSiteResponse($version->name()->value(), $slug->value());
    }
}
