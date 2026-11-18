<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\PostsUnderSectionSlugs;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Event\EventBus;

/**
 * Puts the version last saved of a term on the site, for the first time or in
 * place of the version there. Its slug was checked as it was saved, and stays
 * reserved while the term is edited, so no other term can have taken it. A
 * term off the site that last had that address there loses it: it is this
 * one's now.
 */
final readonly class TermPublisher
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus, PostsUnderSectionSlugs $postsUnderSectionSlugs): void
    {
        $term = new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository);

        $term->publish($postsUnderSectionSlugs);
        $term->save($termRepository, $eventBus);

        // Its address is its last from now on, not that of a term off the
        // site that last had it.
        $slug = $term->publishedSlug();
        $former = null === $slug ? null : $termRepository->findOffSiteByLastSiteSlug($term->taxonomy(), $slug);

        if (null !== $former && !$former->id()->equals($term->id())) {
            $former->loseLastAddress();
            $former->save($termRepository, $eventBus);
        }
    }
}
