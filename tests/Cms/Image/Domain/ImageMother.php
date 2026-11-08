<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Domain;

use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageStatus;
use Cms\Image\Domain\ImageVersion;
use Cms\Image\Domain\Timeline;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds Image aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     ImageMother::create()
 *     ImageMother::create(alt: 'Cuadrado naranja sobre fondo azul')
 *     ImageMother::create(caption: RichTextMother::stored(RichTextMother::paragraph('Foto de archivo.')))
 *     ImageMother::create(createdAt: '2027-01-31 23:59:59')
 *
 * Images are drafts without a caption, and are created, kept under the month
 * of and changed at ClockMother::NOW, unless a test says otherwise; an image
 * is changed when it is created unless updatedAt says otherwise.
 */
final class ImageMother
{
    public const string ALT = 'Cuadrado naranja sobre fondo azul';

    /**
     * @param ?array<array-key, mixed> $caption in the stored rich text format; null for none
     */
    public static function create(
        string $id = ImageIdMother::DEFAULT_VALUE,
        string $alt = self::ALT,
        ?array $caption = null,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
    ): Image {
        return new Image(
            ImageIdMother::create($id),
            new ImageVersion(new Alt($alt), null === $caption ? null : RichText::fromArray($caption)),
            self::path($id, $createdAt),
            ImageStatus::Draft,
            null,
            new Timeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt)),
        );
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
