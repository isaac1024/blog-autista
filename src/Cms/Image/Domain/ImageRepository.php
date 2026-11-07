<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * Outbound port used to persist images.
 */
interface ImageRepository
{
    public function save(Image $image): void;
}
