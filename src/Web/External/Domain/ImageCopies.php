<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Shared\Domain\External\ImageShape;

/**
 * Outbound port that makes and removes the copies of the image of an
 * external resource the site serves, from the file the CMS keeps.
 */
interface ImageCopies
{
    /**
     * Makes the copies of the file the CMS keeps at the path, at the widths
     * of its shape, those not made yet: copies of a file never change.
     */
    public function publish(string $path, ImageShape $shape): void;

    /**
     * Removes the copies of the file, if any.
     */
    public function remove(string $path, ImageShape $shape): void;
}
