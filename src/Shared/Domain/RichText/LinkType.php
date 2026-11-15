<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * The kinds of content a rich text links to, the blog's and those of other
 * sites, each kept under a key of its
 * own next to what links to it, such as "page_id".
 */
enum LinkType: string
{
    case Page = 'page';
    case Post = 'post';

    /**
     * A section or a tag: their ids never meet, whatever their taxonomy.
     */
    case Term = 'term';
    case Author = 'author';

    /**
     * Something of another site, kept as an external resource of the CMS.
     */
    case External = 'external';

    /**
     * The key the id of what is linked is kept under.
     */
    public function key(): string
    {
        return $this->value.'_id';
    }
}
