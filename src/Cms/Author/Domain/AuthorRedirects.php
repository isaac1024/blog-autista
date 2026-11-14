<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Outbound port that tells whether any redirect of the site leads to an
 * author, as the CMS has them now: while one does, the author stays on the
 * site.
 */
interface AuthorRedirects
{
    public function isTargeted(AuthorId $id): bool;
}
