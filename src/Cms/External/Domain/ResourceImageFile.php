<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ImageShape;

/**
 * The image of an external resource before it is kept: a WebP file of the
 * size of its shape, told by its content rather than by its name.
 */
final readonly class ResourceImageFile
{
    /**
     * @param string $path where the file is
     */
    public function __construct(
        private string $path,
        ImageShape $shape,
    ) {
        $size = is_file($path) ? getimagesize($path) : false;

        if (false === $size || 'image/webp' !== $size['mime'] || $shape->width() !== $size[0] || $shape->height() !== $size[1]) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a WebP image of %dx%d pixels.', $path, $shape->width(), $shape->height()));
        }
    }

    public function path(): string
    {
        return $this->path;
    }
}
