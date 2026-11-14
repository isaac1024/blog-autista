<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Domain;

use Cms\Author\Domain\AvatarFile;

final class AvatarFileMother
{
    public const string VALID = 'avatar.webp';

    /**
     * Another valid avatar, with other pixels.
     */
    public const string OTHER = 'other.webp';

    /**
     * One pixel short of the width of every avatar.
     */
    public const string NARROWER = 'narrower.webp';

    /**
     * One pixel short of the height of every avatar.
     */
    public const string SHORTER = 'shorter.webp';

    public const string JPEG = 'avatar.jpg';

    /**
     * A JPEG with the name of a WebP: the content tells what it is.
     */
    public const string JPEG_NAMED_AS_WEBP = 'jpeg-named-as.webp';

    public const string NOT_AN_IMAGE = 'not-an-image.webp';

    public static function create(string $name = self::VALID): AvatarFile
    {
        return new AvatarFile(self::path($name));
    }

    public static function path(string $name = self::VALID): string
    {
        return __DIR__.'/Files/'.$name;
    }
}
