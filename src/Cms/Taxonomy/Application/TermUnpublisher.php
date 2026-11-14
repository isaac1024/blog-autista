<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRedirects;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Event\EventBus;

/**
 * Takes a published term off the site.
 */
final readonly class TermUnpublisher
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus, TermRedirects $termRedirects): void
    {
        $term = new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository);

        $term->unpublish($termRedirects);

        $term->save($termRepository, $eventBus);
    }
}
