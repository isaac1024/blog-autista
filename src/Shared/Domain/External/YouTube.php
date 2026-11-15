<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Videos of YouTube, embedded from youtube-nocookie.com, which keeps nothing
 * in the browser until the video plays, with Spanish captions shown.
 *
 * Its locator is the id of the video, preceded by "shorts/" for a short,
 * which is upright, and followed by "@" and the second it starts at, if any:
 * "dQw4w9WgXcQ", "shorts/dQw4w9WgXcQ" or "dQw4w9WgXcQ@42".
 */
final readonly class YouTube implements Provider
{
    private const string LOCATOR = '#^(shorts/)?([A-Za-z0-9_-]{11})(?:@([1-9][0-9]{0,5}))?$#';

    private const string ID = '/^[A-Za-z0-9_-]{11}$/';

    #[\Override]
    public function name(): string
    {
        return 'youtube';
    }

    #[\Override]
    public function label(): string
    {
        return 'YouTube';
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Video;
    }

    #[\Override]
    public function tellsImage(): bool
    {
        return true;
    }

    #[\Override]
    public function locate(WebAddress $address): ?string
    {
        $segments = $address->segments();
        $start = self::start($address->parameter('t') ?? $address->parameter('start'));

        if ('youtu.be' === $address->host()) {
            return self::locator('', $segments[0] ?? '', $start, 1 === \count($segments));
        }

        if (!\in_array($address->host(), ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            return null;
        }

        if (['watch'] === $segments) {
            return self::locator('', (string) $address->parameter('v'), $start, true);
        }

        if (2 === \count($segments) && \in_array($segments[0], ['shorts', 'embed', 'live'], true)) {
            return self::locator('shorts' === $segments[0] ? 'shorts/' : '', $segments[1], $start, true);
        }

        return null;
    }

    #[\Override]
    public function isLocator(string $locator): bool
    {
        return 1 === preg_match(self::LOCATOR, $locator);
    }

    #[\Override]
    public function isEmbeddable(string $locator): bool
    {
        return $this->isLocator($locator);
    }

    #[\Override]
    public function url(string $locator): string
    {
        [$short, $id, $start] = self::parts($locator);

        if ($short) {
            return \sprintf('https://www.youtube.com/shorts/%s', $id);
        }

        return \sprintf('https://www.youtube.com/watch?v=%s%s', $id, null === $start ? '' : '&t='.$start.'s');
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        [, $id, $start] = self::parts($locator);

        return \sprintf(
            'https://www.youtube-nocookie.com/embed/%s?%s',
            $id,
            http_build_query(['start' => $start, 'cc_load_policy' => 1, 'cc_lang_pref' => 'es', 'hl' => 'es', 'rel' => 0], '', '&'),
        );
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://www.youtube-nocookie.com';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return self::parts($locator)[0] ? EmbedSpace::Portrait : EmbedSpace::Landscape;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://policies.google.com/privacy';
    }

    private static function locator(string $prefix, string $id, ?int $start, bool $fits): ?string
    {
        if (!$fits || 1 !== preg_match(self::ID, $id)) {
            return null;
        }

        return $prefix.$id.('' === $prefix && null !== $start ? '@'.$start : '');
    }

    /**
     * The second a video starts at, as YouTube writes it: "42", "42s" or
     * "1h2m3s".
     */
    private static function start(?string $value): ?int
    {
        if (null === $value || 1 !== preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $value, $matches)) {
            return null;
        }

        $seconds = 3600 * (int) ($matches[1] ?? 0) + 60 * (int) ($matches[2] ?? 0) + (int) ($matches[3] ?? 0);

        return $seconds > 0 && $seconds < 1_000_000 ? $seconds : null;
    }

    /**
     * @return array{bool, string, ?int}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a video of YouTube.', $locator));
        }

        return ['' !== $matches[1], $matches[2], isset($matches[3]) ? (int) $matches[3] : null];
    }
}
