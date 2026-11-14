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
}
