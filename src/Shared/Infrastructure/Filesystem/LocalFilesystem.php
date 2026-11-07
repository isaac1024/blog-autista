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
     * Copies a file, creating the directories of the target it lacks, and
     * gives the copy its permissions, such as 0o644: a copy otherwise keeps
     * those of its source, which for an upload only its owner can read.
     */
    public function copy(string $source, string $target, int $permissions): void
    {
        $this->filesystem->copy($source, $target);
        $this->filesystem->chmod($target, $permissions);
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
