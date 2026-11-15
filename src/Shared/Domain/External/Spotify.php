<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Episodes and shows of Spotify, such as those of a podcast, embedded by
 * its own player, as tall as Spotify draws it: compact for an episode,
 * taller for a show, which lists its episodes.
 *
 * Its locator is what it is and its id, such as
 * "episode/512ojhOuo1ktJprKbVcKyQ".
 */
final readonly class Spotify implements Provider
{
    private const string LOCATOR = '#^(episode|show)/([A-Za-z0-9]{22})$#';

    #[\Override]
    public function name(): string
    {
        return 'spotify';
    }

    #[\Override]
    public function label(): string
    {
        return 'Spotify';
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Audio;
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

        // Spotify may address it in a language of its own, such as /intl-es/.
        if (isset($segments[0]) && str_starts_with($segments[0], 'intl-')) {
            array_shift($segments);
        }

        if ('open.spotify.com' !== $address->host() || 2 !== \count($segments)) {
            return null;
        }

        $locator = $segments[0].'/'.$segments[1];

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
        return 'https://open.spotify.com/'.self::checked($locator);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        return 'https://open.spotify.com/embed/'.self::checked($locator);
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://open.spotify.com';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return str_starts_with(self::checked($locator), 'show/') ? EmbedSpace::Tall : EmbedSpace::Compact;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://www.spotify.com/es/legal/privacy-policy/';
    }

    private static function checked(string $locator): string
    {
        if (1 !== preg_match(self::LOCATOR, $locator)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of an episode or a show of Spotify.', $locator));
        }

        return $locator;
    }
}
