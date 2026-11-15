<?php

declare(strict_types=1);

namespace Web\Tests\External\Domain;

use Web\External\Domain\CmsExternalResourceAvailabilityChanged;

/**
 * Builds the event the CMS sends the site when a check finds an external
 * resource gone, or back:
 *
 *     CmsExternalResourceAvailabilityChangedMother::create(available: false)
 */
final class CmsExternalResourceAvailabilityChangedMother
{
    public static function create(string $id = ExternalResourceIdMother::DEFAULT_VALUE, bool $available = false, int $revision = 2): CmsExternalResourceAvailabilityChanged
    {
        return new CmsExternalResourceAvailabilityChanged($id, $available, $revision);
    }
}
