<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * Outbound port that tells whether an image is in use, such as embedded in a
 * page, being edited or on the site.
 */
interface ImageUsage
{
    public function isInUse(ImageId $id): bool;
}
