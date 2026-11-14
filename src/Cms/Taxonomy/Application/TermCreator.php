<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermSlugAvailability;
use Shared\Domain\Event\EventBus;

/**
 * Creates a term of a taxonomy from the submitted name, deriving its slug
 * from it.
 */
final readonly class TermCreator
{
    public function __construct(
        private string $id,
        private string $taxonomy,
        private string $name,
    ) {}

    public function __invoke(TermRepository $termRepository, EventBus $eventBus): void
    {
        $term = Term::create($this->id, Taxonomy::fromValue($this->taxonomy), $this->name);

        new TermSlugAvailability($term)($termRepository);
        $term->save($termRepository, $eventBus);
    }
}
