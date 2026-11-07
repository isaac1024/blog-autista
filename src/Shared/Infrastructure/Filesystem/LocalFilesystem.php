<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Filesystem;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Files on the local disk. Wraps symfony/filesystem, so contexts never depend
 * on it directly.
 */
final readonly class LocalFilesystem
{
    public function __construct(private Filesystem $filesystem) {}

    /**
     * Copies a file, creating the directories of the target it lacks.
     */
    public function copy(string $source, string $target): void
    {
        $this->filesystem->copy($source, $target);
    }

    /**
     * Removes a file, if it is there. Directories are left alone, so a wrong
     * path can never take a whole tree with it.
     */
    public function remove(string $path): void
    {
        if (is_file($path)) {
            $this->filesystem->remove($path);
        }
    }
}
