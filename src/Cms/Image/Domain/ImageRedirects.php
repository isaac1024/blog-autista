<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * Outbound port that tells whether any redirect of the site leads to an
 * image, as the CMS has them now: while one does, the image stays on the
 * site.
 */
interface ImageRedirects
{
    public function isTargeted(ImageId $id): bool;
}
