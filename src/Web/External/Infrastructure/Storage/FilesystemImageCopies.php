<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Storage;

use Shared\Domain\External\ImageShape;
use Shared\Domain\ExternalImageCopy;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Web\External\Domain\ImageCopies;

/**
 * Outbound adapter that makes the copies of the images of the external
 * resources in the public directory of the site: the largest is the file of
 * the CMS as it is, and the smaller ones are resized from it.
 */
#[AsAlias(ImageCopies::class)]
final readonly class FilesystemImageCopies implements ImageCopies
{
    /**
     * WebP quality of the copies made, as the CMS encodes its file.
     */
    private const int QUALITY = 85;

    public function __construct(
        private LocalFilesystem $filesystem,
        private ImageResizer $imageResizer,
        #[Autowire(param: 'web.external.originals_directory')]
        private string $originalsDirectory,
        #[Autowire(param: 'web.external.public_directory')]
        private string $publicDirectory,
    ) {}

    #[\Override]
    public function publish(string $path, ImageShape $shape): void
    {
        $source = \sprintf('%s/%s', $this->originalsDirectory, $path);

        foreach ($shape->widths() as $width) {
            $copy = new ExternalImageCopy($path, $shape, $width);
            $target = \sprintf('%s/%s', $this->publicDirectory, $copy->file());

            if ($this->filesystem->exists($target)) {
                continue;
            }

            if ($shape->width() === $width) {
                $this->filesystem->copy($source, $target, 0o644);

                continue;
            }

            $this->imageResizer->resize($source, $target, $width, $copy->height(), self::QUALITY);
        }
    }

    #[\Override]
    public function remove(string $path, ImageShape $shape): void
    {
        foreach ($shape->widths() as $width) {
            $this->filesystem->remove(\sprintf('%s/%s', $this->publicDirectory, new ExternalImageCopy($path, $shape, $width)->file()));
        }
    }
}
