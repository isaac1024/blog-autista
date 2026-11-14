<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Whether a post is on the site. A draft has never been published; an
 * unpublished post was, and has been taken off.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * The version the site serves among the one last published, which is
     * none while the post is off the site.
     */
    public function versionOnSite(?PostVersion $published): ?PostVersion
    {
        return self::Published === $this ? $published : null;
    }
}
