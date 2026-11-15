<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Domain;

use Web\Taxonomy\Domain\CmsTermUnpublished;

/**
 * Builds the event the CMS sends the site when it unpublishes a section or
 * a tag:
 *
 *     CmsTermUnpublishedMother::create()
 */
final class CmsTermUnpublishedMother
{
    public static function create(string $id = TermIdMother::DEFAULT_VALUE, int $revision = 2): CmsTermUnpublished
    {
        return new CmsTermUnpublished($id, $revision);
    }
}
