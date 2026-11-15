<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ImageShape;

/**
 * Outbound port that downloads the image a resource tells, and makes of it
 * the file the CMS keeps: cropped to the shape and encoded as WebP, which
 * leaves behind whatever else the file held.
 */
interface ResourceImageFetcher
{
    /**
     * The file made from the image at the address, or null when it can't be
     * downloaded or isn't an image the CMS takes. The caller removes it once
     * kept.
     */
    public function fetch(string $url, ImageShape $shape): ?ResourceImageFile;
}
