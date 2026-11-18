<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;

/**
 * Finds the terms on the site among those given, read at once, as the
 * site shows them, each telling its taxonomy; one off it, an id of none, or what can't be an
 * id, is left out.
 */
final readonly class TermsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, TermOnSiteResponse> keyed by id
     */
    public function __invoke(TermRepository $termRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new TermId($id);
            } catch (DomainError) {
                // Not the id of one: none on the site.
            }
        }

        $found = [];

        foreach ($termRepository->findByIds($ids) as $term) {
            $onSite = TermOnSiteResponse::fromTerm($term);

            if (null !== $onSite) {
                $found[$term->id()->value()] = $onSite;
            }
        }

        return $found;
    }
}
