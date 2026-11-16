<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Outbound port that tells what a post needs to know of the images of the
 * library: whether one exists, as a post only opens with an image the CMS
 * has, and whether it is on the site now, as a post needs its opening image
 * there to be published.
 */
interface PostImages
{
    public function exists(ImageId $id): bool;

    public function isOnSite(ImageId $id): bool;
}
