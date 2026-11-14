<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Outbound port that tells the sections, authors and tags a post may be
 * filed under and signed by, as the CMS has them now, and how the site
 * shows the section and the author while they are on it.
 */
interface Classifiers
{
    public function isSection(SectionId $id): bool;

    public function isAuthor(AuthorId $id): bool;

    public function isTag(TagId $id): bool;

    /**
     * The section as it is on the site, null while it is off it.
     */
    public function sectionOnSite(SectionId $id): ?SectionOnSite;

    /**
     * The author as it is on the site, null while it is off it.
     */
    public function authorOnSite(AuthorId $id): ?AuthorOnSite;
}
