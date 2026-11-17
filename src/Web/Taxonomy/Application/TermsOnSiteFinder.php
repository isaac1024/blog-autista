<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Shared\Domain\DomainError;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * The terms on the site among those given, sections and tags alike, read at
 * once, each telling its taxonomy: a term off the site is left out.
 */
final readonly class TermsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, TermResponse> keyed by id
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new TermId($id);
            } catch (DomainError) {
                // Not the id of a term: none on the site.
            }
        }

        $terms = [];

        foreach ($termRepository->findByIds($ids) as $term) {
            $terms[$term->id()->value()] = TermResponse::fromTerm($term);
        }

        return $terms;
    }
}
