<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Domain;

use Cms\Image\Domain\ImageId;

/**
 * Builds ImageId value objects for tests:
 *
 *     ImageIdMother::create()
 *     ImageIdMother::create('0f8fad5b-d9cb-469f-a165-70867728950e')
 *     ImageIdMother::random()
 */
final class ImageIdMother
{
    public const string DEFAULT_VALUE = '01a1b801-ce58-723d-8234-b76d9105d0af';

    public static function create(string $value = self::DEFAULT_VALUE): ImageId
    {
        return new ImageId($value);
    }

    /**
     * An ImageId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): ImageId
    {
        return ImageId::random();
    }
}
