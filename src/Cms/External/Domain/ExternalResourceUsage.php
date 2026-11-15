<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Outbound port that tells whether an external resource is in use, such as
 * a text that links it, or a menu, in the version being edited or on the
 * site.
 */
interface ExternalResourceUsage
{
    public function isInUse(ExternalResourceId $id): bool;
}
