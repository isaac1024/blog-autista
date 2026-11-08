<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Outbound port that tells whether a page is in use, such as linked from
 * another page, in the version being edited or on the site.
 */
interface PageUsage
{
    public function isInUse(PageId $id): bool;
}
