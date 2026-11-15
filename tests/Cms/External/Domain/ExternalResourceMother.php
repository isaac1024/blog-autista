<?php

declare(strict_types=1);

namespace Cms\Tests\External\Domain;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\Language;
use Cms\External\Domain\ResourceDetails;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceTitle;
use Cms\External\Domain\ResourceUrl;
use Cms\External\Domain\ResourceVersion;
use Cms\External\Domain\Timeline;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds external resources for tests, of each kind, with sensible defaults
 * that can be overridden through named arguments:
 *
 *     ExternalResourceMother::video()
 *     ExternalResourceMother::video(status: ResourceStatus::Published)
 *     ExternalResourceMother::post(details: [...])
 *
 * Resources are created at ClockMother::NOW and, when published, published
 * then, with the version being edited on the site unless a test gives
 * another.
 */
final class ExternalResourceMother
{
    public const string ID = '01a1e2a0-0000-7000-8000-000000000001';

    public const string IMAGE = '01a1e2a0-0000-7000-8000-0000000000a1.webp';

    public const string OTHER_IMAGE = '01a1e2a0-0000-7000-8000-0000000000a2.webp';

    public const string VIDEO_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    public const string VIDEO = 'dQw4w9WgXcQ';

    public const string POST_URL = 'https://bsky.app/profile/did:plc:z72i7hdynmk6r22z27h6tvur/post/3l6oveex3ii2l';

    public const string POST = 'did:plc:z72i7hdynmk6r22z27h6tvur/3l6oveex3ii2l';

    /**
     * What a post tells, enough to publish it.
     */
    public const array POST_DETAILS = ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-17', 'text' => "Hola.\n\nAdiós.", 'with_media' => false];

    public const string ARTICLE_URL = 'https://www.example.org/noticia.html';

    public const string STUDY_URL = 'https://doi.org/10.1038/nature12373';

    public const array STUDY_DETAILS = ['authors' => 'G. Kucsko, P. C. Maurer', 'journal' => 'Nature', 'year' => 2013];

    public static function video(
        string $id = self::ID,
        string $url = self::VIDEO_URL,
        string $locator = self::VIDEO,
        string $title = 'Un vídeo',
        ?string $image = self::IMAGE,
        ResourceStatus $status = ResourceStatus::Draft,
        ?ResourceVersion $published = null,
        Availability $availability = Availability::Available,
    ): ExternalResource {
        return self::resource($id, ResourceKind::Video, 'youtube', self::version(ResourceKind::Video, $url, $locator, $title, [], $image), $status, $published, $availability);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    public static function post(
        string $id = self::ID,
        string $url = self::POST_URL,
        string $locator = self::POST,
        array $details = self::POST_DETAILS,
        ResourceStatus $status = ResourceStatus::Draft,
        Availability $availability = Availability::Available,
    ): ExternalResource {
        return self::resource($id, ResourceKind::SocialPost, 'bluesky', self::version(ResourceKind::SocialPost, $url, $locator, 'Publicación de Bluesky en Bluesky', $details, null), $status, null, $availability);
    }

    public static function article(
        string $id = self::ID,
        string $url = self::ARTICLE_URL,
        string $title = 'Una noticia',
        ?string $image = null,
        ResourceStatus $status = ResourceStatus::Draft,
        Availability $availability = Availability::Available,
    ): ExternalResource {
        return self::resource($id, ResourceKind::Article, null, self::version(ResourceKind::Article, $url, null, $title, ['publisher' => 'El Medio', 'published_on' => '2026-11-01'], $image, 'De qué trata.'), $status, null, $availability);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    public static function study(string $id = self::ID, array $details = self::STUDY_DETAILS, ResourceStatus $status = ResourceStatus::Draft): ExternalResource
    {
        return self::resource($id, ResourceKind::Study, null, self::version(ResourceKind::Study, self::STUDY_URL, null, 'Nanometre-scale thermometry in a living cell', $details, null, null, 'en'), $status, null, Availability::Available);
    }

    /**
     * A resource of the kind, by the provider given, if any. Published, it
     * is on the site with the version given, or else with the one edited.
     */
    public static function resource(
        string $id,
        ResourceKind $kind,
        ?string $provider,
        ResourceVersion $version,
        ResourceStatus $status,
        ?ResourceVersion $published,
        Availability $availability,
    ): ExternalResource {
        $timeline = Timeline::start(ClockMother::now());
        $onSite = ResourceStatus::Draft === $status ? null : $published ?? $version;

        return new ExternalResource(
            new ExternalResourceId($id),
            $kind,
            $provider,
            $version,
            $status,
            $onSite,
            $availability,
            null,
            null === $onSite ? $timeline : $timeline->published(ClockMother::now()),
            null === $onSite ? 0 : 1,
        );
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    public static function version(
        ResourceKind $kind,
        string $url,
        ?string $locator,
        string $title,
        array $details,
        ?string $image,
        ?string $summary = null,
        string $language = 'es',
    ): ResourceVersion {
        return new ResourceVersion(
            new ResourceUrl($url),
            $locator,
            new ResourceTitle($title),
            $summary,
            Language::from($language),
            ResourceDetails::fromPrimitives($kind, $details),
            null === $image ? null : new ResourceImagePath($image),
        );
    }
}
