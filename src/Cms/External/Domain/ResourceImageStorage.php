<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Outbound port that keeps the images of the external resources, out of the
 * public directory.
 */
interface ResourceImageStorage
{
    /**
     * Keeps the file under the path, taking it from where it was.
     */
    public function save(ResourceImagePath $path, ResourceImageFile $file): void;

    public function delete(ResourceImagePath $path): void;
}
