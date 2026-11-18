<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\Slug;

/**
 * Outbound port that tells which posts on the site carry the slug of a
 * section in their address: a post keeps the slug its section had when it
 * was published, until it is published again.
 */
interface PostsUnderSectionSlugs
{
    /**
     * @return array<string, int> how many posts, by the id of the section they are under on the site
     */
    public function countedBySection(Slug $slug): array;
}
