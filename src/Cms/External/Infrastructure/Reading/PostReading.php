<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Shared\Domain\External\Provider;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteUnreachable;

/**
 * Reads a post of a social network from its provider: who wrote it, from
 * which account, the day, its text and its language, as each provider
 * tells them, in a way of its own.
 */
final readonly class PostReading
{
    private const int MAX_BYTES = 1_000_000;

    public function __construct(
        private JsonReading $json,
        private RemoteHttp $http,
    ) {}

    /**
     * What the provider tells of the post, or null when it doesn't answer as
     * it does for one of its posts.
     */
    public function read(Provider $provider, string $locator): ?Reading
    {
        $post = match ($provider->name()) {
            'bluesky' => $this->bluesky($provider, $locator),
            'x' => $this->xPost($provider, $locator),
            'mastodon' => $this->mastodon($provider, $locator),
            'threads' => $this->threads($provider, $locator),
            default => null,
        };

        if (null === $post) {
            return null;
        }

        $language = $post['language'] ?? null;
        unset($post['language']);
        $locator = $post['locator'] ?? $locator;
        unset($post['locator']);
        $title = \sprintf('Publicación de %s en %s', $post['author'] ?? $post['handle'] ?? $provider->label(), $provider->label());
        $post['text'] = null === ($post['text'] ?? null) ? null : mb_substr((string) $post['text'], 0, 3000);

        return new Reading(
            $provider->kind(),
            $provider->name(),
            (string) $locator,
            $title,
            null,
            null === $language ? null : Language::tryFrom(strtolower(substr((string) $language, 0, 2))),
            $post + ['author' => null, 'handle' => null, 'posted_on' => null, 'text' => null, 'with_media' => false],
            [],
            true,
        );
    }

    /**
     * Bluesky tells the permanent id of the account, which its frame takes,
     * the text of the post, its language and the moment it was written.
     *
     * @return ?array<string, null|bool|string>
     */
    private function bluesky(Provider $provider, string $locator): ?array
    {
        $data = $this->json->object(ProviderEndpoints::forResource($provider, $locator));
        $html = null === $data ? null : JsonReading::text($data, 'html');

        if (null === $data || null === $html) {
            return null;
        }

        $resolved = 1 === preg_match('#data-bluesky-uri="at://(did:[^/"]+)/app\.bsky\.feed\.post/([a-z0-9]+)"#', $html, $matches) ? $matches[1].'/'.$matches[2] : null;
        [$text, $language] = PostText::quoted($html);
        // Its author is written as "Name (@handle)".
        [$author, $handle] = self::nameAndHandle(JsonReading::text($data, 'author_name'));

        return [
            'locator' => null !== $resolved && $provider->isLocator($resolved) ? $resolved : $locator,
            'author' => $author,
            'handle' => $handle,
            'posted_on' => 1 === preg_match('#>(\d{4}-\d{2}-\d{2})T[^<]*</a>\s*</blockquote>#', $html, $date) ? $date[1] : null,
            'text' => $text,
            'language' => $language,
        ];
    }

    /**
     * X tells who wrote the post, its text, and the day, in English, in the
     * quote it writes; a photo or a video shows as a link to pic.x.com.
     *
     * @return ?array<string, null|bool|string>
     */
    private function xPost(Provider $provider, string $locator): ?array
    {
        $data = $this->json->object(ProviderEndpoints::forResource($provider, $locator));
        $html = null === $data ? null : JsonReading::text($data, 'html');

        if (null === $data || null === $html) {
            return null;
        }

        [$text, $language] = PostText::quoted($html);
        // Its photos and videos show in the text as links to them, which the
        // facade tells otherwise.
        $text = null === $text ? null : trim((string) preg_replace('#\s*\bpic\.(?:twitter|x)\.com/\S+#', '', $text));
        $authorUrl = JsonReading::text($data, 'author_url');
        $day = 1 === preg_match('#<a href="[^"]*/status/[0-9]+[^"]*">([A-Z][a-z]+ \d{1,2}, \d{4})</a>\s*</blockquote>#', $html, $date)
            ? \DateTimeImmutable::createFromFormat('!F j, Y', $date[1])
            : false;

        return [
            'author' => JsonReading::text($data, 'author_name'),
            'handle' => null === $authorUrl ? null : '@'.basename($authorUrl),
            'posted_on' => false === $day ? null : $day->format('Y-m-d'),
            'text' => $text,
            'with_media' => 1 === preg_match('#pic\.(?:twitter|x)\.com/#', $html),
            'language' => $language,
        ];
    }

    /**
     * A Mastodon server tells all of a post through its API, and answering
     * as Mastodon is what tells its address is one of a post of Mastodon.
     *
     * @return ?array<string, null|bool|string>
     */
    private function mastodon(Provider $provider, string $locator): ?array
    {
        $data = $this->json->object(ProviderEndpoints::forResource($provider, $locator));
        $account = $data['account'] ?? null;
        $content = null === $data ? null : JsonReading::text($data, 'content');

        if (null === $data || !\is_array($account) || null === $content) {
            return null;
        }

        $acct = JsonReading::text($account, 'acct');
        $created = JsonReading::text($data, 'created_at');
        $media = $data['media_attachments'] ?? [];

        return [
            'author' => JsonReading::text($account, 'display_name') ?? JsonReading::text($account, 'username'),
            'handle' => null === $acct ? null : '@'.(str_contains($acct, '@') ? $acct : $acct.'@'.explode('/', $locator)[0]),
            'posted_on' => null === $created ? null : substr($created, 0, 10),
            'text' => PostText::fromHtml($content),
            'with_media' => \is_array($media) && [] !== $media,
            'language' => JsonReading::text($data, 'language'),
        ];
    }

    /**
     * Threads tells nothing of a post through its endpoint, but its page
     * does: who wrote it, as "Name (@handle) on Threads", in the language
     * asked for, and its text.
     *
     * @return ?array<string, null|bool|string>
     */
    private function threads(Provider $provider, string $locator): ?array
    {
        try {
            $response = $this->http->get($provider->url($locator), self::MAX_BYTES, 'text/html');
        } catch (RemoteUnreachable) {
            return null;
        }

        if (!$response->isSuccessful()) {
            return null;
        }

        $page = PageMetadata::fromHtml($response->body);
        [$author, $handle] = self::nameAndHandle($page->title());

        return ['author' => $author, 'handle' => $handle, 'text' => $page->summary()];
    }

    /**
     * The name and the handle in "Name (@handle)", whatever follows, or the
     * whole as the name when it has no handle.
     *
     * @return array{?string, ?string}
     */
    private static function nameAndHandle(?string $value): array
    {
        $value = trim((string) $value);

        if (1 === preg_match('/^(.*?)\s*\((@[^)\s]+)\)/u', $value, $matches)) {
            return ['' === $matches[1] ? null : $matches[1], $matches[2]];
        }

        return ['' === $value ? null : $value, null];
    }
}
