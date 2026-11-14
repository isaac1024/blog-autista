<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

/**
 * Outbound port that tells whether a term is in use, such as a post filed under it,
 * in the version being edited or on the site.
 */
interface TermUsage
{
    public function isInUse(TermId $id): bool;
}
