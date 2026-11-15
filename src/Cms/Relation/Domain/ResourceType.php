<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

/**
 * Kinds of resource of the CMS a relation joins: one uses the other.
 */
enum ResourceType: string
{
    case Page = 'page';
    case Image = 'image';
    case Settings = 'settings';
    case Author = 'author';
    case Post = 'post';

    /**
     * A section or a tag: their ids never meet, whatever their taxonomy.
     */
    case Term = 'term';

    /**
     * Something of another site, linked or embedded as the resource it is.
     */
    case External = 'external';
}
