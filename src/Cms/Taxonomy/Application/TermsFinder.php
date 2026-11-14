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
        $terms = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $term = $termRepository->find(new TermId($id));
            } catch (DomainError) {
                continue;
            }

            if (null !== $term) {
                $terms[] = TermResponse::fromTerm($term);
            }
        }

        return $terms;
    }
}
