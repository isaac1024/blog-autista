<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Event\EventBus;

/**
 * Throws away what was edited on a published term since it was published,
 * back to the version on the site.
 */
final readonly class TermChangesDiscarder
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus): void
    {
        $term = new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository);

        $term->discardChanges();

        $term->save($termRepository, $eventBus);
    }
}
