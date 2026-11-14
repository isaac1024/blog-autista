<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermRepository;

/**
 * Deletes an existing term, failing when it doesn't exist.
 */
final readonly class TermDeleter
{
    public function __construct(
        private string $taxonomy,
        private string $id,
    ) {}

    public function __invoke(TermRepository $termRepository): void
    {
        new TermOfTaxonomy($this->taxonomy, $this->id)($termRepository)->delete($termRepository);
    }
}
