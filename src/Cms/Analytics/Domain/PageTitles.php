<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * Outbound port that names the pages of the CMS; a page deleted has no name.
 */
interface PageTitles
{
    /**
     * @param list<string> $pageIds
     *
     * @return array<string, string> the titles, keyed by id, of those that still exist
     */
    public function titlesOf(array $pageIds): array;
}
