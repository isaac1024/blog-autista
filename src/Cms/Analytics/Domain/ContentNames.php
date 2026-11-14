<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * Outbound port that names what the site counts the visits of, as the CMS
 * keeps it: a page or a post by its title, a section, a tag or an author by
 * its name. One deleted has no name.
 */
interface ContentNames
{
    /**
     * @param Dimension    $kind one of Dimension::contents()
     * @param list<string> $ids
     *
     * @return array<string, array{name: string, kind: string}> keyed by id, of those that still exist; kind says what each is, such as "page", "section" or "tag"
     */
    public function namesOf(Dimension $kind, array $ids): array;
}
