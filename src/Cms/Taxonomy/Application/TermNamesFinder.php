<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;

/**
 * Finds the names of the terms among the ids given, as edited, without
 * reading the rest of each, each with its taxonomy: an id of none, or anything that can't be an id,
 * is left out.
 */
final readonly class TermNamesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, array{name: string, taxonomy: string}> by id
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new TermId($id);
            } catch (DomainError) {
                // Not the id of one: none to find.
            }
        }

        return array_map(
            static fn (array $term): array => ['name' => $term['name'], 'taxonomy' => $term['taxonomy']->value],
            $termRepository->namesOf($ids),
        );
    }
}
