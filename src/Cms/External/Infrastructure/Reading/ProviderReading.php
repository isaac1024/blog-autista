<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Cms\External\Domain\Reading;
use Shared\Domain\External\Provider;
use Shared\Domain\External\ResourceKind;

/**
 * Reads a resource a provider embeds by asking the provider: the title and
 * the image of a video or an audio from its oEmbed endpoint, and a post as
 * PostReading reads it.
 */
final readonly class ProviderReading
{
    public function __construct(
        private JsonReading $json,
        private PostReading $postReading,
    ) {}

    /**
     * What the provider tells of the resource, or null when it doesn't
     * answer as it does for one of its resources.
     */
    public function read(Provider $provider, string $locator): ?Reading
    {
        if (ResourceKind::SocialPost === $provider->kind()) {
            return $this->postReading->read($provider, $locator);
        }

        $data = $this->json->object(ProviderEndpoints::forResource($provider, $locator));

        if (null === $data) {
            return null;
        }

        $thumbnail = JsonReading::text($data, 'thumbnail_url');
        $images = [...self::youtubeImages($provider, $locator), ...(null === $thumbnail ? [] : [$thumbnail])];
        $details = ResourceKind::Audio === $provider->kind() ? ['show' => null] : [];

        return new Reading($provider->kind(), $provider->name(), $locator, JsonReading::text($data, 'title'), null, null, $details, $images, true);
    }

    /**
     * Where YouTube keeps the largest picture of a wide video, besides the
     * one its endpoint tells, which has black bands above and below.
     *
     * @return list<string>
     */
    private static function youtubeImages(Provider $provider, string $locator): array
    {
        if ('youtube' !== $provider->name() || 1 !== preg_match('#^([A-Za-z0-9_-]{11})#', $locator, $matches)) {
            return [];
        }

        return [\sprintf('https://i.ytimg.com/vi/%s/maxresdefault.jpg', $matches[1])];
    }
}
