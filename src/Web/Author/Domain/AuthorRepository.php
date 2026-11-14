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
     * The author served at the slug.
     */
    public function findBySlug(string $slug): ?Author;
}
