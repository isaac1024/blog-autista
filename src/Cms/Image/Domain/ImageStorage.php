<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * Outbound port that keeps the files of the images, apart from their data.
 */
interface ImageStorage
{
    public function save(ImagePath $path, ImageFile $file): void;

    public function delete(ImagePath $path): void;
}
