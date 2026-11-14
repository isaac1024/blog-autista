<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * A copy of the avatar of an author, one of the sizes the site serves, all
 * square. The copies of an avatar share a name made of the slug of its
 * author and a short hash of its file, so a new avatar, or a new address,
 * never reuses the name of copies browsers keep for good.
 */
final readonly class AvatarCopy
{
    /**
     * Sides of the copies, smallest first.
     */
    public const array SIZES = [96, 200, 400];

    public const int LARGEST = 400;

    private const string URL = '/media/autores/';

    /**
     * @param string $name the name its copies share, as named() gives it
     * @param int    $size one of the sides of the copies
     */
    public function __construct(
        private string $name,
        private int $size,
    ) {
        if (1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the name of the copies of an avatar.', $name));
        }

        if (!\in_array($size, self::SIZES, true)) {
            throw new \InvalidArgumentException(\sprintf('No copy is %d pixels wide.', $size));
        }
    }

    /**
     * The name the copies of the avatar kept at the path share, for the
     * author at the slug.
     *
     * @param string $path where the CMS keeps the file of the avatar
     */
    public static function named(string $slug, string $path): string
    {
        return \sprintf('%s-%s', $slug, substr(hash('sha256', $path), 0, 10));
    }

    /**
     * Its file name, relative to where the site keeps the copies of the avatars.
     */
    public function file(): string
    {
        return \sprintf('%s-%dw.webp', $this->name, $this->size);
    }

    /**
     * Its address on the site, relative to its root.
     */
    public function url(): string
    {
        return self::URL.$this->file();
    }
}
