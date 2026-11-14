<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * Kinds of resource of the CMS an address of the site can lead to.
 *
 * Images are apart from the rest: an image is asked for by a page, to be
 * shown, so an address of an image only ever leads to another image, and
 * an image is reached from no other address. Any other kind, such as pages
 * or posts, leads to any kind but images.
 */
enum ResourceType: string
{
    case Page = 'page';
    case Image = 'image';
    case Post = 'post';

    /**
     * A section or a tag: their ids never meet, whatever their taxonomy.
     */
    case Term = 'term';
    case Author = 'author';

    public function isImage(): bool
    {
        return self::Image === $this;
    }
}
