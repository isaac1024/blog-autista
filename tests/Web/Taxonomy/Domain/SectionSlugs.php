<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Domain;

use Doctrine\DBAL\Connection;
use Web\Tests\Post\Domain\CmsPostPublishedMother;

/**
 * The slug of a section as the site keeps it once the CMS published the
 * section, for tests that put posts on the site without it: the addresses
 * of posts name their sections by it.
 */
trait SectionSlugs
{
    private static function saveSectionSlug(string $sectionId = CmsPostPublishedMother::SECTION, string $slug = 'vida-diaria'): void
    {
        self::getContainer()->get(Connection::class)->executeStatement(
            'REPLACE INTO web_section_slug (section_id, slug) VALUES (:id, :slug)',
            ['id' => $sectionId, 'slug' => $slug],
        );
    }
}
