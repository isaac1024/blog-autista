<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Shared\Domain\External\Provider;

/**
 * Where each provider tells of one of its resources: its oEmbed endpoint for
 * most, and the API of the server for Mastodon, which also answers whether
 * the resource is still there.
 */
final readonly class ProviderEndpoints
{
    /**
     * The address that tells of the resource, and answers 404, or the like,
     * once it is gone.
     */
    public static function forResource(Provider $provider, string $locator): string
    {
        $url = rawurlencode($provider->url($locator));

        return match ($provider->name()) {
            'youtube' => 'https://www.youtube.com/oembed?format=json&url='.$url,
            'tiktok' => 'https://www.tiktok.com/oembed?url='.$url,
            'spotify' => 'https://open.spotify.com/oembed?url='.$url,
            'bluesky' => 'https://embed.bsky.app/oembed?url='.$url,
            'x' => 'https://publish.twitter.com/oembed?omit_script=true&dnt=true&url='.$url,
            'threads' => 'https://graph.threads.net/v1.0/oembed?url='.$url,
            'mastodon' => self::status($locator),
            default => throw new \InvalidArgumentException(\sprintf('No endpoint tells of the resources of "%s".', $provider->name())),
        };
    }

    /**
     * Where a Mastodon server tells all of one of its posts.
     */
    private static function status(string $locator): string
    {
        [$host, , $id] = explode('/', $locator);

        return \sprintf('https://%s/api/v1/statuses/%s', $host, $id);
    }
}
