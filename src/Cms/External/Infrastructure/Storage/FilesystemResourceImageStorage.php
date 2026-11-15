<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Storage;

use Cms\External\Domain\ResourceImageFile;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Outbound adapter that keeps the images of the external resources in a
 * directory of the server, out of the public one.
 */
#[AsAlias(ResourceImageStorage::class)]
final readonly class FilesystemResourceImageStorage implements ResourceImageStorage
{
    public function __construct(
        private LocalFilesystem $filesystem,
        #[Autowire(param: 'cms.external.image_directory')]
        private string $directory,
    ) {}

    #[\Override]
    public function save(ResourceImagePath $path, ResourceImageFile $file): void
    {
        // Readable by the PHP process that serves it through the CMS.
        $this->filesystem->copy($file->path(), $this->absolute($path), 0o644);
        $this->filesystem->remove($file->path());
    }

    #[\Override]
    public function delete(ResourceImagePath $path): void
    {
        $this->filesystem->remove($this->absolute($path));
    }

    private function absolute(ResourceImagePath $path): string
    {
        return \sprintf('%s/%s', $this->directory, $path->value());
    }
}
