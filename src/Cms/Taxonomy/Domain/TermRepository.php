<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read terms.
 */
interface TermRepository
{
    /**
     * @throws TermSlugAlreadyTaken when another term of the taxonomy is saved with the same slug meanwhile
     */
    public function save(Term $term): void;

    public function delete(Term $term): void;

    public function find(TermId $id): ?Term;

    /**
     * The terms among those given, of any taxonomy, read at once, in no
     * order; an id of no term is left out.
     *
     * @param list<TermId> $ids
     *
     * @return list<Term>
     */
    public function findByIds(array $ids): array;

    /**
     * The names, as edited, of the terms among those given, by id, with the
     * taxonomy of each, read without the rest of each; an id of none is left
     * out.
     *
     * @param list<TermId> $ids
     *
     * @return array<string, array{name: string, taxonomy: Taxonomy}>
     */
    public function namesOf(array $ids): array;

    /**
     * The term of the taxonomy edited with the slug, or on the site with it.
     */
    public function findBySlug(Taxonomy $taxonomy, Slug $slug): ?Term;

    /**
     * The term of the taxonomy on the site with the slug.
     */
    public function findOnSiteBySlug(Taxonomy $taxonomy, Slug $slug): ?Term;

    /**
     * The term of the taxonomy off the site whose last address there had
     * the slug.
     */
    public function findOffSiteByLastSiteSlug(Taxonomy $taxonomy, Slug $slug): ?Term;

    /**
     * @return list<Term>
     */
    public function matching(Criteria $criteria): array;
}
