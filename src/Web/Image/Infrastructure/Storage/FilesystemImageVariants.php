<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Storage;

use Shared\Domain\ImageCopy;
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

        foreach (array_keys(ImageCopy::SIZES) as $width) {
            $copy = new ImageCopy($path, $width);
            $target = $this->kept($copy);

            if ($this->filesystem->exists($target)) {
                continue;
            }

            if (ImageCopy::LARGEST === $width) {
                $this->filesystem->copy($source, $target, 0o644);

                continue;
            }

            $this->imageResizer->resize($source, $target, $copy->width(), $copy->height(), self::QUALITY);
        }
    }

    #[\Override]
    public function remove(string $path): void
    {
        foreach (array_keys(ImageCopy::SIZES) as $width) {
            $this->filesystem->remove($this->kept(new ImageCopy($path, $width)));
        }
    }

    /**
     * Where a copy is kept.
     */
    private function kept(ImageCopy $copy): string
    {
        return \sprintf('%s/%s', $this->publicDirectory, $copy->name());
    }
}
