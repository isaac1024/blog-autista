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
     * The pages on the site among those given; any other is left out.
     *
     * @param list<PageId> $ids
     *
     * @return list<Page>
     */
    public function findByIds(array $ids): array;

    /**
     * The page served at the slug.
     */
    public function findBySlug(string $slug): ?Page;

    /**
     * The page served at the root of the site, which has no slug.
     */
    public function findHome(): ?Page;

    /**
     * The page that tells what the site keeps in the browsers of its readers,
     * while it is on the site.
     */
    public function findCookiePolicy(): ?Page;
}
