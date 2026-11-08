<?php

declare(strict_types=1);

namespace Web\Tests\Image\Domain;

use Web\Image\Domain\ImageId;

/**
 * Builds ImageId value objects of the site for tests:
 *
 *     ImageIdMother::create()
 *     ImageIdMother::random()
 */
final class ImageIdMother
{
    public const string DEFAULT_VALUE = '01a1b801-ce58-723d-8234-b76d9105d0af';

    public static function create(string $value = self::DEFAULT_VALUE): ImageId
    {
        return new ImageId($value);
    }

    public static function random(): ImageId
    {
        return ImageId::random();
    }
}
