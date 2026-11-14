<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

/**
 * The taxonomy a term on the site belongs to, which tells where it is served.
 */
enum Taxonomy: string
{
    case Section = 'section';
    case Tag = 'tag';
}
