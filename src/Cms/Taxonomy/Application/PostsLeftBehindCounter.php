<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\PostsUnderSectionSlugs;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * Counts the posts on the site that publishing a section with another slug
 * leaves at its address of now, until each is published again.
 */
final readonly class PostsLeftBehindCounter
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository, PostsUnderSectionSlugs $postsUnderSectionSlugs): int
    {
        return new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository)->postsLeftBehind($postsUnderSectionSlugs);
    }
}
