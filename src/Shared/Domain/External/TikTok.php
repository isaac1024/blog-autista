<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Videos of TikTok, embedded by its own player, upright. TikTok tells
 * neither their title nor their image, so their facade goes without one.
 *
 * Its locator is the name of the account and the id of the video, such as
 * "scout2015/6718335390845095173".
 */
final readonly class TikTok implements Provider
{
    private const string LOCATOR = '#^([A-Za-z0-9_.]{1,40})/([0-9]{1,25})$#';

    #[\Override]
    public function name(): string
    {
        return 'tiktok';
    }

    #[\Override]
    public function label(): string
    {
        return 'TikTok';
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Video;
    }

    #[\Override]
    public function tellsImage(): bool
    {
        return false;
    }

    #[\Override]
    public function locate(WebAddress $address): ?string
    {
        $segments = $address->segments();

        if ('tiktok.com' !== $address->host() || 3 !== \count($segments) || !str_starts_with($segments[0], '@') || 'video' !== $segments[1]) {
            return null;
        }

        $locator = substr($segments[0], 1).'/'.$segments[2];

        return $this->isLocator($locator) ? $locator : null;
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
        [$account, $id] = self::parts($locator);

        return \sprintf('https://www.tiktok.com/@%s/video/%s', $account, $id);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        return \sprintf('https://www.tiktok.com/player/v1/%s?description=1&music_info=1&rel=0', self::parts($locator)[1]);
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://www.tiktok.com';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return EmbedSpace::Portrait;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://www.tiktok.com/legal/page/eea/privacy-policy/es';
    }

    /**
     * @return array{string, string}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a video of TikTok.', $locator));
        }

        return [$matches[1], $matches[2]];
    }
}
