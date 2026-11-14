<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

/**
 * Outbound port that tells whether any redirect of the site leads to a term,
 * as the CMS has them now: while one does, the term stays on the site.
 */
interface TermRedirects
{
    public function isTargeted(TermId $id): bool;
}
