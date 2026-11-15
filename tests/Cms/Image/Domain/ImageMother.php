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
 *     ImageMother::published()
 *     ImageMother::published(alt: 'Otro texto', publishedAlt: ImageMother::ALT)
 *     ImageMother::unpublished()
 *
 * Images are drafts without a caption, and are created, kept under the month
 * of and changed at ClockMother::NOW, unless a test says otherwise; an image
 * is changed when it is created unless updatedAt says otherwise.
 */
final class ImageMother
{
    public const string ALT = 'Cuadrado naranja sobre fondo azul';

    /**
     * A caption enough to publish an image.
     */
    public const array CAPTION = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Ilustración de archivo.']]]],
    ];

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
            new Timeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt), null, null),
            0,
        );
    }

    /**
     * On the site, with the version being edited unless a test gives the one
     * published apart, as an image with unpublished changes.
     *
     * @param array<array-key, mixed>  $caption
     * @param ?array<array-key, mixed> $publishedCaption
     */
    public static function published(
        string $id = ImageIdMother::DEFAULT_VALUE,
        string $alt = self::ALT,
        array $caption = self::CAPTION,
        ?string $publishedAlt = null,
        ?array $publishedCaption = null,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        int $publicationRevision = 1,
    ): Image {
        return self::onTheSite(ImageStatus::Published, $id, $alt, $caption, $publishedAlt ?? $alt, $publishedCaption ?? $caption, $createdAt, $updatedAt, $firstPublishedAt, $lastPublishedAt, $publicationRevision);
    }

    /**
     * Taken off the site, keeping the version it had there, the one being
     * edited unless a test gives it apart, as an image edited since.
     *
     * @param array<array-key, mixed>  $caption
     * @param ?array<array-key, mixed> $publishedCaption
     */
    public static function unpublished(
        string $id = ImageIdMother::DEFAULT_VALUE,
        string $alt = self::ALT,
        array $caption = self::CAPTION,
        ?string $publishedAlt = null,
        ?array $publishedCaption = null,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        int $publicationRevision = 2,
    ): Image {
        return self::onTheSite(ImageStatus::Unpublished, $id, $alt, $caption, $publishedAlt ?? $alt, $publishedCaption ?? $caption, $createdAt, $updatedAt, $firstPublishedAt, $lastPublishedAt, $publicationRevision);
    }

    /**
     * Where the image created with an id and a date is kept, spelt out rather
     * than worked out as the code does, so tests catch it going elsewhere.
     */
    public static function path(string $id = ImageIdMother::DEFAULT_VALUE, string $createdAt = ClockMother::NOW): ImagePath
    {
        return new ImagePath(\sprintf('%s/%s.webp', ClockMother::now($createdAt)->format('Y/m'), $id));
    }

    /**
     * @param array<array-key, mixed> $caption
     * @param array<array-key, mixed> $publishedCaption
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private static function onTheSite(
        ImageStatus $status,
        string $id,
        string $alt,
        array $caption,
        string $publishedAlt,
        array $publishedCaption,
        string $createdAt,
        ?string $updatedAt,
        string $firstPublishedAt,
        string $lastPublishedAt,
        int $publicationRevision,
    ): Image {
        return new Image(
            ImageIdMother::create($id),
            new ImageVersion(new Alt($alt), RichText::fromArray($caption)),
            self::path($id, $createdAt),
            $status,
            new ImageVersion(new Alt($publishedAlt), RichText::fromArray($publishedCaption)),
            new Timeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt), ClockMother::now($firstPublishedAt), ClockMother::now($lastPublishedAt)),
            $publicationRevision,
        );
    }
}
