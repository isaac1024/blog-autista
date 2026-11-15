<?php

declare(strict_types=1);

namespace Web\Tests\External\Domain;

use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;
use Web\External\Domain\CmsExternalResourcePublished;

/**
 * Builds the event the CMS sends the site when it publishes an external
 * resource, a video of YouTube with a landscape image unless a test says
 * otherwise, as the site reads it, or its body as it travels:
 *
 *     CmsExternalResourcePublishedMother::create(title: 'Otro título')
 *     ['title' => 'Otro título'] + CmsExternalResourcePublishedMother::body()
 */
final class CmsExternalResourcePublishedMother
{
    /**
     * The file of its image, as the CMS keeps it.
     */
    public const string IMAGE = '01a1e2a0-0000-7000-8000-0000000000a1.webp';

    /**
     * The body of the event as it travels from the CMS, its first revision.
     *
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        return [
            'kind' => 'video',
            'provider' => 'youtube',
            'locator' => 'dQw4w9WgXcQ',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Un vídeo',
            'summary' => null,
            'language' => 'es',
            'details' => [],
            'image' => self::IMAGE,
            'image_shape' => 'landscape',
            'available' => true,
            'revision' => 1,
        ];
    }

    public static function create(
        string $id = ExternalResourceIdMother::DEFAULT_VALUE,
        string $title = 'Un vídeo',
        ?string $image = self::IMAGE,
        ?ImageShape $imageShape = ImageShape::Landscape,
        bool $available = true,
        int $revision = 1,
    ): CmsExternalResourcePublished {
        return new CmsExternalResourcePublished(
            $id,
            ResourceKind::Video,
            'youtube',
            'dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $title,
            null,
            'es',
            [],
            $image,
            $imageShape,
            $available,
            $revision,
        );
    }
}
