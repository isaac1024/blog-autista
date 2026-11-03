<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read pages.
 */
interface PageRepository
{
    /**
     * @throws SlugAlreadyTaken when another page is saved with the same slug meanwhile
     */
    public function save(Page $page): void;

    public function delete(Page $page): void;

    public function find(PageId $id): ?Page;

    public function findBySlug(PageSlug $slug): ?Page;

    public function findByType(PageType $type): ?Page;

    /**
     * @return list<Page>
     */
    public function matching(Criteria $criteria): array;
}
