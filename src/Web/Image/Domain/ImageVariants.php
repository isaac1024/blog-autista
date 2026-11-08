<?php

declare(strict_types=1);

namespace Web\Image\Domain;

/**
 * Outbound port that keeps the copies of an image the site serves, apart from
 * the file the CMS keeps: 1200, 800 and 400 pixels wide, all 16:9.
 */
interface ImageVariants
{
    /**
     * Makes the copies of the file the CMS keeps at the path, those not made
     * yet: a file never changes, so a copy made once stays right.
     */
    public function publish(string $path): void;

    /**
     * Removes the copies of the file the CMS keeps at the path, if any.
     */
    public function remove(string $path): void;
}
