<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;

/**
 * Finds the terms among those given, whatever their taxonomy, in the order
 * given; an id of no term, or anything that can't be an id, is left out.
 */
final readonly class TermsFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<TermResponse>
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $ids = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $ids[] = new TermId($id);
            } catch (DomainError) {
                // Not the id of a term: none to find.
            }
        }

        $found = [];

        foreach ($termRepository->findByIds($ids) as $term) {
            $found[$term->id()->value()] = TermResponse::fromTerm($term);
        }

        // The repository answers in its own order.
        return array_values(array_filter(array_map(
            static fn (TermId $id): ?TermResponse => $found[$id->value()] ?? null,
            $ids,
        )));
    }
}
