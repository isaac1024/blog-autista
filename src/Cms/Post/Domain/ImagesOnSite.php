<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Outbound port that tells whether an image of the library is on the site
 * now, as a post needs its opening image to be published.
 */
interface ImagesOnSite
{
    public function isOnSite(ImageId $id): bool;
}
