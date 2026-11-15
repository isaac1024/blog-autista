<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read pages.
 */
interface PageRepository
{
    /**
     * @throws PageSlugAlreadyTaken    when another page is saved with the same slug meanwhile
     * @throws UniquePageAlreadyExists when another page of the same unique type is saved meanwhile
     */
    public function save(Page $page): void;

    public function delete(Page $page): void;

    public function find(PageId $id): ?Page;

    /**
     * The pages among those given; an id of no page is left out.
     *
     * @param list<PageId> $ids
     *
     * @return list<Page>
     */
    public function findByIds(array $ids): array;

    /**
     * The page edited with the slug, or on the site with it.
     */
    public function findBySlug(Slug $slug): ?Page;

    /**
     * The page on the site with the slug.
     */
    public function findOnSiteBySlug(Slug $slug): ?Page;

    /**
     * The page off the site whose last address on it had the slug.
     */
    public function findOffSiteByLastSiteSlug(Slug $slug): ?Page;

    public function findByType(PageType $type): ?Page;

    /**
     * @return list<Page>
     */
    public function matching(Criteria $criteria): array;
}
