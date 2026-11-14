<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read authors.
 */
interface AuthorRepository
{
    /**
     * @throws AuthorSlugAlreadyTaken when another author is saved with the same slug meanwhile
     */
    public function save(Author $author): void;

    public function delete(Author $author): void;

    public function find(AuthorId $id): ?Author;

    /**
     * The authors among those given; an id of no author is left out.
     *
     * @param list<AuthorId> $ids
     *
     * @return list<Author>
     */
    public function findByIds(array $ids): array;

    /**
     * The author edited with the slug, or on the site with it.
     */
    public function findBySlug(Slug $slug): ?Author;

    /**
     * The author on the site with the slug.
     */
    public function findOnSiteBySlug(Slug $slug): ?Author;

    /**
     * The author off the site whose last address there had the slug.
     */
    public function findOffSiteByLastSiteSlug(Slug $slug): ?Author;

    /**
     * @return list<Author>
     */
    public function matching(Criteria $criteria): array;
}
