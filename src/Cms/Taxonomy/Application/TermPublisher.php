<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Event\EventBus;

/**
 * Puts the version last saved of a term on the site, for the first time or in
 * place of the version there. Its slug was checked as it was saved, and stays
 * reserved while the term is edited, so no other term can have taken it.
 */
final readonly class TermPublisher
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus): void
    {
        $term = new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository);

        $term->publish();

        $term->save($termRepository, $eventBus);
    }
}
