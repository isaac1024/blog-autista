<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ExternalComponents;

/**
 * How a text paints an external resource: what a provider embeds, behind a
 * facade that frames it from the provider alone, at the space it takes and
 * with the policy it is under; anything else as a card that leads to it.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 */
final class ExternalComponentsTest extends TestCase
{
    /**
     * @param array{embedUrl: string, frameOrigin: string, space: string, privacyUrl: string} $expected
     */
    #[DataProvider('embedded')]
    public function testItFramesWhatAProviderEmbedsFromThatProviderAlone(string $kind, string $provider, string $locator, array $expected): void
    {
        $view = self::view($kind, $provider, $locator);
        $component = ExternalComponents::component($view, false);

        self::assertSame('Shared:ExternalEmbed', $component->name);
        self::assertSame($expected, array_intersect_key($component->props, $expected));
        self::assertSame($expected['frameOrigin'], ExternalComponents::frameOrigin($view));
    }

    /**
     * @return iterable<string, array{string, string, string, array{embedUrl: string, frameOrigin: string, space: string, privacyUrl: string}}>
     */
    public static function embedded(): iterable
    {
        $youtube = static fn (string $query, string $space): array => ['embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?'.$query.'cc_load_policy=1&cc_lang_pref=es&hl=es&rel=0', 'frameOrigin' => 'https://www.youtube-nocookie.com', 'space' => $space, 'privacyUrl' => 'https://policies.google.com/privacy'];

        yield 'a video of YouTube, without cookies' => ['video', 'youtube', 'dQw4w9WgXcQ', $youtube('', 'landscape')];
        yield 'a video of YouTube from where it starts' => ['video', 'youtube', 'dQw4w9WgXcQ@3723', $youtube('start=3723&', 'landscape')];
        yield 'a short of YouTube, upright' => ['video', 'youtube', 'shorts/dQw4w9WgXcQ', $youtube('', 'portrait')];
        yield 'a video of TikTok, upright' => ['video', 'tiktok', 'scout2015/6718335390845095173', ['embedUrl' => 'https://www.tiktok.com/player/v1/6718335390845095173?description=1&music_info=1&rel=0', 'frameOrigin' => 'https://www.tiktok.com', 'space' => 'portrait', 'privacyUrl' => 'https://www.tiktok.com/legal/page/eea/privacy-policy/es']];
        yield 'an episode of Spotify, compact' => ['audio', 'spotify', 'episode/512ojhOuo1ktJprKbVcKyQ', ['embedUrl' => 'https://open.spotify.com/embed/episode/512ojhOuo1ktJprKbVcKyQ', 'frameOrigin' => 'https://open.spotify.com', 'space' => 'compact', 'privacyUrl' => 'https://www.spotify.com/es/legal/privacy-policy/']];
        yield 'a show of Spotify, tall' => ['audio', 'spotify', 'show/2MAi0BvDc6GTFvKFPXnkCL', ['embedUrl' => 'https://open.spotify.com/embed/show/2MAi0BvDc6GTFvKFPXnkCL', 'frameOrigin' => 'https://open.spotify.com', 'space' => 'tall', 'privacyUrl' => 'https://www.spotify.com/es/legal/privacy-policy/']];
        yield 'a post of Bluesky by the permanent id of its account' => ['social_post', 'bluesky', 'did:plc:z72i7hdynmk6r22z27h6tvur/3l6oveex3ii2l', ['embedUrl' => 'https://embed.bsky.app/embed/did:plc:z72i7hdynmk6r22z27h6tvur/app.bsky.feed.post/3l6oveex3ii2l', 'frameOrigin' => 'https://embed.bsky.app', 'space' => 'reported', 'privacyUrl' => 'https://bsky.social/about/support/privacy-policy']];
        yield 'a post of Mastodon, from its own server' => ['social_post', 'mastodon', 'mastodon.social/Gargron/1', ['embedUrl' => 'https://mastodon.social/@Gargron/1/embed', 'frameOrigin' => 'https://mastodon.social', 'space' => 'reported', 'privacyUrl' => 'https://mastodon.social/privacy-policy']];
        yield 'a post of Threads' => ['social_post', 'threads', 'mosseri/DCo4WiOvCB0', ['embedUrl' => 'https://www.threads.com/@mosseri/post/DCo4WiOvCB0/embed/', 'frameOrigin' => 'https://www.threads.com', 'space' => 'reported', 'privacyUrl' => 'https://help.instagram.com/515230437301944']];
        yield 'a post of X, without tracking' => ['social_post', 'x', 'jack/20', ['embedUrl' => 'https://platform.twitter.com/embed/Tweet.html?id=20&dnt=true&lang=es', 'frameOrigin' => 'https://platform.twitter.com', 'space' => 'reported', 'privacyUrl' => 'https://x.com/es/privacy']];
    }

    /**
     * A post of Bluesky named by the handle of its account has no frame;
     * an article or a study no provider frames at all.
     */
    #[DataProvider('cards')]
    public function testItPaintsAsACardWhatNoProviderFrames(string $kind, ?string $provider, ?string $locator): void
    {
        $view = self::view($kind, $provider, $locator);
        $component = ExternalComponents::component($view, false);

        self::assertSame('Shared:ContentExternal', $component->name);
        self::assertSame(['kind' => $kind, 'url' => $view['url'], 'title' => 'Un recurso'], array_intersect_key($component->props, ['kind' => true, 'url' => true, 'title' => true]));
        self::assertNull(ExternalComponents::frameOrigin($view));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function cards(): iterable
    {
        yield 'a post of Bluesky by the handle of its account' => ['social_post', 'bluesky', 'bsky.app/3l6oveex3ii2l'];
        yield 'an article' => ['article', null, null];
        yield 'a study' => ['study', null, null];
        yield 'a video without its locator' => ['video', 'youtube', null];
    }

    /**
     * @return ExternalView
     */
    private static function view(string $kind, ?string $provider, ?string $locator): array
    {
        return ['kind' => $kind, 'provider' => $provider, 'locator' => $locator, 'url' => 'https://www.example.org/recurso', 'title' => 'Un recurso', 'summary' => null, 'language' => 'es', 'details' => [], 'image' => null, 'cookies' => null];
    }
}
