<?php

declare(strict_types=1);

namespace Web\Author\Domain;

/**
 * Outbound port used to persist and read the authors on the site.
 */
interface AuthorRepository
{
    public function save(Author $author): void;

    public function delete(Author $author): void;

    public function find(AuthorId $id): ?Author;

    /**
     * The authors on the site among those given, read at once, in no order;
     * an id of none is left out.
     *
     * @param list<AuthorId> $ids
     *
     * @return list<Author>
     */
    public function findByIds(array $ids): array;

    /**
     * The author served at the slug.
     */
    public function findBySlug(string $slug): ?Author;
}
