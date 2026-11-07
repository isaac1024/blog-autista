<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

/**
 * The file of an image before it is kept: a WebP image of the size of every
 * image, told by its content rather than by its name.
 */
final readonly class ImageFile
{
    /**
     * @param string $path where the file is; empty when none came
     */
    public function __construct(private string $path)
    {
        if ('' === $path) {
            throw InvalidImageFile::missing();
        }

        $size = is_file($path) ? getimagesize($path) : false;

        if (false === $size || 'image/webp' !== $size['mime']) {
            throw InvalidImageFile::notWebp();
        }

        if (Image::WIDTH !== $size[0] || Image::HEIGHT !== $size[1]) {
            throw InvalidImageFile::wrongSize(Image::WIDTH, Image::HEIGHT);
        }
    }

    public function path(): string
    {
        return $this->path;
    }
}
