<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermSlugAvailability;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Saves everything edited on an existing term: its name, slug and search
 * engine settings. A published term stays on the site as it was until it is
 * published again.
 */
final readonly class TermUpdater
{
    public function __construct(
        private string $taxonomy,
        private string $id,
        private string $name,
        private string $slug,
        private string $metaDescription,
        private string $robots,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $term = new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository);

        $term->edit($this->name, $this->slug, $this->metaDescription, $this->robots);

        new TermSlugAvailability($term)($termRepository);
        $term->save($termRepository, $eventBus, $transaction);
    }
}
