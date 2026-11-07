<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Storage;

use Cms\Image\Domain\ImageFile;
use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageStorage;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Outbound adapter that keeps the files of the images in a local directory,
 * out of the public one: the site serves its own copies of them.
 */
#[AsAlias(ImageStorage::class)]
final readonly class FilesystemImageStorage implements ImageStorage
{
    public function __construct(
        private LocalFilesystem $filesystem,
        #[Autowire(param: 'cms.image.storage_directory')]
        private string $directory,
    ) {}

    #[\Override]
    public function save(ImagePath $path, ImageFile $file): void
    {
        $this->filesystem->copy($file->path(), $this->absolute($path));
    }

    #[\Override]
    public function delete(ImagePath $path): void
    {
        $this->filesystem->remove($this->absolute($path));
    }

    private function absolute(ImagePath $path): string
    {
        return \sprintf('%s/%s', $this->directory, $path->value());
    }
}
