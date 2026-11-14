<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * The file of an avatar before it is kept: a square WebP image of the size of
 * every avatar, told by its content rather than by its name.
 */
final readonly class AvatarFile
{
    public const int SIZE = 400;

    /**
     * @param string $path where the file is
     */
    public function __construct(private string $path)
    {
        $size = is_file($path) ? getimagesize($path) : false;

        if (false === $size || 'image/webp' !== $size['mime']) {
            throw InvalidAvatarFile::notWebp();
        }

        if (self::SIZE !== $size[0] || self::SIZE !== $size[1]) {
            throw InvalidAvatarFile::wrongSize(self::SIZE, self::SIZE);
        }
    }

    public function path(): string
    {
        return $this->path;
    }
}
