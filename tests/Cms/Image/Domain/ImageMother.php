<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Domain;

use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\Timeline;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds Image aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     ImageMother::create()
 *     ImageMother::create(alt: 'Cuadrado naranja sobre fondo azul')
 *     ImageMother::create(createdAt: '2027-01-31 23:59:59')
 *
 * Images are created, and kept under the month of, ClockMother::NOW unless a
 * test says otherwise.
 */
final class ImageMother
{
    public const string ALT = 'Cuadrado naranja sobre fondo azul';

    public static function create(
        string $id = ImageIdMother::DEFAULT_VALUE,
        string $alt = self::ALT,
        string $createdAt = ClockMother::NOW,
    ): Image {
        $imageId = ImageIdMother::create($id);
        $date = ClockMother::now($createdAt);

        return new Image($imageId, new Alt($alt), self::path($id, $createdAt), null, new Timeline($date, $date));
    }

    /**
     * Where the image created with an id and a date is kept, spelt out rather
     * than worked out as the code does, so tests catch it going elsewhere.
     */
    public static function path(string $id = ImageIdMother::DEFAULT_VALUE, string $createdAt = ClockMother::NOW): ImagePath
    {
        return new ImagePath(\sprintf('%s/%s.webp', ClockMother::now($createdAt)->format('Y/m'), $id));
    }
}
