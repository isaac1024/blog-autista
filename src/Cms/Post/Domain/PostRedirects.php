<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Outbound port that tells whether any redirect of the site leads to a post,
 * as the CMS has them now: while one does, the post stays on the site.
 */
interface PostRedirects
{
    public function isTargeted(PostId $id): bool;
}
