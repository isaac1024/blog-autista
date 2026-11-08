<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Storage;

use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Web\Image\Domain\ImageVariants;

/**
 * Outbound adapter that keeps the copies of the images the site serves in its
 * public directory, apart from the files the CMS keeps, named after the
 * file of the CMS and the width: 2026/11/<id>-800w.webp. Their addresses
 * never change, since the file of an image never does.
 *
 * The widest copy is the file of the CMS as it is: the browser already
 * encoded it at its best. The others are made from it.
 */
#[AsAlias(ImageVariants::class)]
final readonly class FilesystemImageVariants implements ImageVariants
{
    /**
     * Widths of the copies, with their heights, all 16:9.
     */
    private const array SIZES = [1200 => 675, 800 => 450, 400 => 225];

    /**
     * WebP quality of the copies made, as the browser encodes the file of the CMS.
     */
    private const int QUALITY = 85;

    public function __construct(
        private LocalFilesystem $filesystem,
        private ImageResizer $imageResizer,
        #[Autowire(param: 'web.image.originals_directory')]
        private string $originalsDirectory,
        #[Autowire(param: 'web.image.public_directory')]
        private string $publicDirectory,
    ) {}

    #[\Override]
    public function publish(string $path): void
    {
        $source = \sprintf('%s/%s', $this->originalsDirectory, $path);

        foreach (self::SIZES as $width => $height) {
            $target = $this->copy($path, $width);

            if ($this->filesystem->exists($target)) {
                continue;
            }

            if (1200 === $width) {
                $this->filesystem->copy($source, $target, 0o644);

                continue;
            }

            $this->imageResizer->resize($source, $target, $width, $height, self::QUALITY);
        }
    }

    #[\Override]
    public function remove(string $path): void
    {
        foreach (array_keys(self::SIZES) as $width) {
            $this->filesystem->remove($this->copy($path, $width));
        }
    }

    /**
     * Where the copy of a width of the file at the path is kept.
     */
    private function copy(string $path, int $width): string
    {
        return \sprintf('%s/%s-%dw.webp', $this->publicDirectory, preg_replace('/\.webp$/', '', $path), $width);
    }
}
