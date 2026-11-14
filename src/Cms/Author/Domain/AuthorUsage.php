<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Outbound port that tells whether an author is in use, such as a post signed by it,
 * in the version being edited or on the site.
 */
interface AuthorUsage
{
    public function isInUse(AuthorId $id): bool;
}
