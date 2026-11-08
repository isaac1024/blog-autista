<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Whether a page is on the site. A draft has never been published; an
 * unpublished page was, and has been taken off.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * The version the site serves among the one last published, which is
     * none while the page is off the site.
     */
    public function versionOnSite(?PageVersion $published): ?PageVersion
    {
        return self::Published === $this ? $published : null;
    }
}
