<?php

declare(strict_types=1);

namespace Web\Page\Domain;

/**
 * Outbound port used to persist and read the pages on the site.
 */
interface PageRepository
{
    public function save(Page $page): void;

    public function delete(Page $page): void;

    public function find(PageId $id): ?Page;

    /**
     * The page served at the slug; an empty slug is the home page.
     */
    public function findBySlug(string $slug): ?Page;
}
