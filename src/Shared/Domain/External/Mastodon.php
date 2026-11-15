<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Posts of Mastodon, on whichever server of the network they were written,
 * embedded from that server, which reports the height of its frame.
 *
 * An address of a post looks like that of other services, so the CMS asks
 * the server before taking it for one. Its locator is the host of the
 * server, the name of the account and the id of the post, such as
 * "mastodon.social/Gargron/1".
 */
final readonly class Mastodon implements Provider
{
    private const string LOCATOR = '#^([a-z0-9-]+(?:\.[a-z0-9-]+)+)/([A-Za-z0-9_]{1,30})/([0-9]{1,25})$#';

    #[\Override]
    public function name(): string
    {
        return 'mastodon';
    }

    #[\Override]
    public function label(): string
    {
        return 'Mastodon';
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::SocialPost;
    }

    #[\Override]
    public function tellsImage(): bool
    {
        // A post shows its text, not an image.
        return false;
    }

    #[\Override]
    public function locate(WebAddress $address): ?string
    {
        $segments = $address->segments();

        if (2 !== \count($segments) || !str_starts_with($segments[0], '@')) {
            return null;
        }

        $locator = \sprintf('%s/%s/%s', $address->host(), substr($segments[0], 1), $segments[1]);

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
        [$host, $account, $id] = self::parts($locator);

        return \sprintf('https://%s/@%s/%s', $host, $account, $id);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        return $this->url($locator).'/embed';
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://'.self::parts($locator)[0];
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return EmbedSpace::Reported;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return \sprintf('https://%s/privacy-policy', self::parts($locator)[0]);
    }

    /**
     * @return array{string, string, string}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a post of Mastodon.', $locator));
        }

        return [$matches[1], $matches[2], $matches[3]];
    }
}
