<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;

/**
 * Finds the term of a taxonomy at an address of the site by its slug: the
 * one on the site with it, or the one off the site whose last address it
 * was. A slug that can't be one has no term.
 */
final readonly class TermAtAddressFinder
{
    private Taxonomy $taxonomy;

    /**
     * @param string $taxonomy one of the Taxonomy values, such as TermResponse::SECTION
     */
    public function __construct(
        string $taxonomy,
        private string $slug,
    ) {
        $this->taxonomy = Taxonomy::fromValue($taxonomy);
    }

    public function onSite(TermRepository $termRepository): ?TermResponse
    {
        $slug = $this->slug();
        $term = null === $slug ? null : $termRepository->findOnSiteBySlug($this->taxonomy, $slug);

        return null === $term ? null : TermResponse::fromTerm($term);
    }

    public function lastOffSite(TermRepository $termRepository): ?TermResponse
    {
        $slug = $this->slug();
        $term = null === $slug ? null : $termRepository->findOffSiteByLastSiteSlug($this->taxonomy, $slug);

        return null === $term ? null : TermResponse::fromTerm($term);
    }

    private function slug(): ?Slug
    {
        try {
            return new Slug($this->slug);
        } catch (DomainError) {
            return null;
        }
    }
}
