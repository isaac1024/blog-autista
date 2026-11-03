<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read pages.
 */
interface PageRepository
{
    public function save(Page $page): void;

    /**
     * @return list<Page>
     */
    public function matching(Criteria $criteria): array;
}
