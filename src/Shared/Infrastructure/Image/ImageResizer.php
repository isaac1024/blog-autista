<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Image;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Shared\Infrastructure\Filesystem\LocalFilesystem;

/**
 * Makes smaller copies of images. Wraps imagine/imagine, on GD as Hetzner's
 * PHP has it, so contexts never depend on it directly.
 */
final readonly class ImageResizer
{
    public function __construct(private LocalFilesystem $filesystem) {}

    /**
     * Saves a copy of the image at the source, resized to the width and
     * height, as WebP at the quality, from 0 to 100, readable by everyone, so
     * the web server can serve it.
     */
    public function resize(string $source, string $target, int $width, int $height, int $quality): void
    {
        $this->filesystem->makeDirectory(\dirname($target));

        new Imagine()
            ->open($source)
            ->resize(new Box($width, $height))
            ->save($target, ['format' => 'webp', 'webp_quality' => $quality]);

        $this->filesystem->setPermissions($target, 0o644);
    }

    /**
     * Saves a copy of the image at the source that fills the width and the
     * height, cropped around its middle where its shape differs, and grown
     * when it is smaller, as WebP at the quality, from 0 to 100, readable by
     * everyone. Only what the image shows is saved: nothing else its file
     * held, such as where it was taken.
     *
     * @throws \RuntimeException when the source isn't an image GD reads
     */
    public function cover(string $source, string $target, int $width, int $height, int $quality): void
    {
        $this->filesystem->makeDirectory(\dirname($target));

        new Imagine()
            ->open($source)
            ->strip()
            ->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_OUTBOUND | ManipulatorInterface::THUMBNAIL_FLAG_UPSCALE)
            ->save($target, ['format' => 'webp', 'webp_quality' => $quality]);

        $this->filesystem->setPermissions($target, 0o644);
    }
}
