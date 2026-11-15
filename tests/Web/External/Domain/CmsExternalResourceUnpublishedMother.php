<?php

declare(strict_types=1);

namespace Web\Tests\External\Domain;

use Web\External\Domain\CmsExternalResourceUnpublished;

/**
 * Builds the event the CMS sends the site when it unpublishes an external
 * resource:
 *
 *     CmsExternalResourceUnpublishedMother::create()
 */
final class CmsExternalResourceUnpublishedMother
{
    public static function create(string $id = ExternalResourceIdMother::DEFAULT_VALUE, int $revision = 2): CmsExternalResourceUnpublished
    {
        return new CmsExternalResourceUnpublished($id, $revision);
    }
}
