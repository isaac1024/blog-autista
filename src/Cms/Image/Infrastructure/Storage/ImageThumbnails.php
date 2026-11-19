<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Storage;

use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The small copies of the images that the CMS shows in its lists and
 * pickers, which draw them a few hundred pixels wide at most: the original
 * is 1200 wide. One is made the first time it is asked for, so it can
 * always be made again, and goes with its image.
 */
final readonly class ImageThumbnails
{
    /**
     * Twice the widest the CMS draws one, 240 pixels in the editor of a
     * text, in the shape of every image, 16:9.
     */
    private const int WIDTH = 480;

    private const int HEIGHT = 270;

    private const int QUALITY = 80;

    public function __construct(
        private LocalFilesystem $filesystem,
        private ImageResizer $imageResizer,
        #[Autowire(param: 'cms.image.storage_directory')]
        private string $originalsDirectory,
        #[Autowire(param: 'cms.image.thumbnail_directory')]
        private string $directory,
    ) {}

    /**
     * Where the thumbnail of the image at the path is, made now if it isn't
     * yet; null when the image has no file to make it from.
     *
     * @param string $path the path of the image's file, as ImagePath holds it
     */
    public function made(string $path): ?string
    {
        $thumbnail = $this->thumbnail($path);

        if ($this->filesystem->exists($thumbnail)) {
            return $thumbnail;
        }

        $original = \sprintf('%s/%s', $this->originalsDirectory, $path);

        if (!$this->filesystem->exists($original)) {
            return null;
        }

        $this->imageResizer->cover($original, $thumbnail, self::WIDTH, self::HEIGHT, self::QUALITY);

        return $thumbnail;
    }

    /**
     * @param string $path the path of the image's file, as ImagePath holds it
     */
    public function remove(string $path): void
    {
        $this->filesystem->remove($this->thumbnail($path));
    }

    private function thumbnail(string $path): string
    {
        return \sprintf('%s/%s', $this->directory, $path);
    }
}
