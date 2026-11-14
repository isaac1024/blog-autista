<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Storage;

use Cms\Author\Domain\AvatarFile;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Outbound adapter that keeps the files of the avatars in a directory of the
 * server, out of the public one.
 */
#[AsAlias(AvatarStorage::class)]
final readonly class FilesystemAvatarStorage implements AvatarStorage
{
    public function __construct(
        private LocalFilesystem $filesystem,
        #[Autowire(param: 'cms.author.avatar_directory')]
        private string $directory,
    ) {}

    #[\Override]
    public function save(AvatarPath $path, AvatarFile $file): void
    {
        // Readable by the PHP process that serves it through the CMS.
        $this->filesystem->copy($file->path(), $this->absolute($path), 0o644);
    }

    #[\Override]
    public function delete(AvatarPath $path): void
    {
        $this->filesystem->remove($this->absolute($path));
    }

    private function absolute(AvatarPath $path): string
    {
        return \sprintf('%s/%s', $this->directory, $path->value());
    }
}
