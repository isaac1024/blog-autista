<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Outbound port that tells whether what a visit was to is on the site.
 */
interface PublishedContents
{
    public function contains(Content $content): bool;
}
