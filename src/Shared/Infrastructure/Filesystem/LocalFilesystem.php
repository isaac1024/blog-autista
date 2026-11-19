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
        $this->writeWhole($target, $permissions, function (string $partial) use ($source): void {
            $this->filesystem->copy($source, $partial, true);
        });
    }

    /**
     * Writes a file whole or not at all, creating the directories of the
     * target it lacks: the writing goes to a file of its own beside the
     * target, which takes the target's place, with its permissions, once
     * written. A file left halfway, as by a process that dies, is never
     * the target, which is served once there and never written again.
     *
     * @param \Closure(string): void $write writes the file at the path it is given
     */
    public function writeWhole(string $target, int $permissions, \Closure $write): void
    {
        $directory = \dirname($target);
        $this->filesystem->mkdir($directory);
        // In the same directory, so taking the target's place is one rename.
        $partial = \sprintf('%s/.%s.%s.partial', $directory, basename($target), bin2hex(random_bytes(6)));

        try {
            $write($partial);
            $this->filesystem->chmod($partial, $permissions);
            $this->filesystem->rename($partial, $target, true);
        } finally {
            $this->remove($partial);
        }
    }

    /**
     * A new file in the directory of temporary files, holding the content
     * given, or nothing, which its caller removes once done with it.
     */
    public function temporary(string $prefix, string $content): string
    {
        $path = $this->filesystem->tempnam(sys_get_temp_dir(), $prefix);
        $this->filesystem->dumpFile($path, $content);

        return $path;
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
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
