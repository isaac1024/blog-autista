<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;

/**
 * Finds the terms of a taxonomy among those given, in the order given; an
 * id of no such term, or anything that can't be an id, is left out.
 */
final readonly class TermsOfTaxonomyByIdFinder
{
    private Taxonomy $taxonomy;

    /**
     * @param string       $taxonomy one of the Taxonomy values, such as TermResponse::SECTION
     * @param list<string> $ids
     */
    public function __construct(
        string $taxonomy,
        private array $ids,
    ) {
        $this->taxonomy = Taxonomy::fromValue($taxonomy);
    }

    /**
     * @return list<TermResponse>
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new TermId($id);
            } catch (DomainError) {
                // Not the id of a term: none to find.
            }
        }

        $found = [];

        foreach ($termRepository->findByIds($ids) as $term) {
            if ($term->taxonomy() === $this->taxonomy) {
                $found[$term->id()->value()] = TermResponse::fromTerm($term);
            }
        }

        // In the order asked, the repository answering in its own.
        return array_values(array_filter(array_map(
            static fn (TermId $id): ?TermResponse => $found[$id->value()] ?? null,
            $ids,
        )));
    }
}
