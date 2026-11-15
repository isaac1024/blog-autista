<?php

declare(strict_types=1);

namespace Cms\Tests\External\Domain;

use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Shared\Domain\External\ResourceKind;

/**
 * Builds what reading the address of an external resource tells, a video of
 * YouTube with an image to fetch unless a test says otherwise:
 *
 *     ReadingMother::video()
 *     ReadingMother::video(reached: false)
 */
final class ReadingMother
{
    public const string IMAGE_URL = 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg';

    /**
     * @param list<string> $images where its images are, the best first
     */
    public static function video(
        string $title = 'Un vídeo leído',
        string $locator = ExternalResourceMother::VIDEO,
        array $images = [self::IMAGE_URL],
        bool $reached = true,
    ): Reading {
        return new Reading(ResourceKind::Video, 'youtube', $locator, $title, 'De qué trata.', Language::Spanish, [], $images, $reached);
    }

    public static function article(): Reading
    {
        return new Reading(ResourceKind::Article, null, null, 'Una noticia', null, null, ['publisher' => 'El Medio'], [], true);
    }
}
