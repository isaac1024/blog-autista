<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Outbound port that tells whether a post is in use, such as linked from a
 * page, another post, a bio or a caption, being edited or on the site.
 */
interface PostUsage
{
    public function isInUse(PostId $id): bool;
}
