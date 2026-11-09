<?php

declare(strict_types=1);

namespace Web\Tests\Image\Domain;

use Shared\Domain\Event\EventId;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Web\Image\Domain\CmsImagePublished;

/**
 * Builds the event of the CMS publishing an image, as the site takes it:
 *
 *     CmsImagePublishedMother::create()
 *     CmsImagePublishedMother::create(alt: 'Otro texto', occurredOn: '2026-11-07 11:00:00')
 *     CmsImagePublishedMother::body()
 */
final class CmsImagePublishedMother
{
    public const string ALT = 'Cuadrado naranja sobre fondo azul';

    /**
     * A caption as the CMS stores and sends it.
     */
    public const array CAPTION = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Ilustración de archivo.']]]],
    ];

    /**
     * Where the CMS keeps the file of the image.
     */
    public const string PATH = '2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp';

    /**
     * A file as the CMS keeps it, a WebP of 1200 by 675 pixels, to put at its
     * path in the tests that make the copies from it.
     */
    public static function file(): string
    {
        return __DIR__.'/Files/original.webp';
    }

    /**
     * The body of the event as it travels from the CMS.
     *
     * @param array<array-key, mixed> $caption
     *
     * @return array<string, mixed>
     */
    public static function body(string $alt = self::ALT, array $caption = self::CAPTION, string $path = self::PATH): array
    {
        return ['alt' => $alt, 'caption' => $caption, 'path' => $path];
    }

    /**
     * @param array<array-key, mixed> $caption
     */
    public static function create(
        string $id = ImageIdMother::DEFAULT_VALUE,
        string $alt = self::ALT,
        array $caption = self::CAPTION,
        string $path = self::PATH,
        string $occurredOn = ClockMother::NOW,
        int $revision = 1,
    ): CmsImagePublished {
        return new CmsImagePublished($id, $alt, RichText::fromArray($caption), $path, EventId::random()->value(), ClockMother::now($occurredOn), $revision);
    }
}
