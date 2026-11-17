<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

/**
 * Outbound port used to persist and read the terms on the site.
 */
interface TermRepository
{
    public function save(Term $term): void;

    public function delete(Term $term): void;

    public function find(TermId $id): ?Term;

    /**
     * The terms on the site among those given, of any taxonomy; any other is
     * left out.
     *
     * @param list<TermId> $ids
     *
     * @return list<Term>
     */
    public function findByIds(array $ids): array;

    /**
     * The term of the taxonomy served at the slug.
     */
    public function findBySlug(Taxonomy $taxonomy, string $slug): ?Term;
}
