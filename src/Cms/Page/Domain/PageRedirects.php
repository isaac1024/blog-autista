<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Outbound port that tells whether any redirect of the site leads to a page,
 * as the CMS has them now: while one does, the page stays on the site.
 */
interface PageRedirects
{
    public function isTargeted(PageId $id): bool;
}
