<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Outbound port that tells whether a page is on the site.
 */
interface PublishedPages
{
    public function contains(PageId $pageId): bool;
}
