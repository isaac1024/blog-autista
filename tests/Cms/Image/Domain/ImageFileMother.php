<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Domain;

use Cms\Image\Domain\ImageFile;

/**
 * Files uploaded as images, kept under Files/, and ImageFile value objects
 * built from them:
 *
 *     ImageFileMother::create()
 *     ImageFileMother::path(ImageFileMother::LARGER)
 *
 * Only the default one, a WebP of 1200 by 675 pixels, makes a valid image.
 */
final class ImageFileMother
{
    public const string VALID = 'image.webp';

    public const string LARGER = 'larger.webp';

    /**
     * One pixel short of the width of every image.
     */
    public const string NARROWER = 'narrower.webp';

    /**
     * One pixel short of the height of every image.
     */
    public const string SHORTER = 'shorter.webp';

    public const string JPEG = 'image.jpg';

    /**
     * A JPEG with the name of a WebP: the content tells what it is.
     */
    public const string JPEG_NAMED_AS_WEBP = 'jpeg-named-as.webp';

    public const string NOT_AN_IMAGE = 'not-an-image.webp';

    public static function create(string $name = self::VALID): ImageFile
    {
        return new ImageFile(self::path($name));
    }

    public static function path(string $name = self::VALID): string
    {
        return __DIR__.'/Files/'.$name;
    }
}
