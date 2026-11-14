<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * The kinds of page of the site whose visits are counted, each by the
 * dimension of the same name.
 */
enum ContentType: string
{
    case Page = 'page';
    case Post = 'post';

    /**
     * The page of a section or a tag: their ids never meet.
     */
    case Term = 'term';
    case Author = 'author';
}
