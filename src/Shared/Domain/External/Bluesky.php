<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Posts of Bluesky, embedded from embed.bsky.app, which reports the height
 * of its frame.
 *
 * Its locator is the account and the key of the post, such as
 * "did:plc:z72i7hdynmk6r22z27h6tvur/3l6oveex3ii2l". An address may name the
 * account by its handle, which can change; the frame only takes its
 * permanent id, a "did:", which the CMS asks Bluesky for.
 */
final readonly class Bluesky implements Provider
{
    private const string LOCATOR = '#^((?:did:[a-z]+:[A-Za-z0-9._:%-]{1,200})|(?:[a-z0-9-]+(?:\.[a-z0-9-]+)+))/([a-z0-9]{1,20})$#';

    #[\Override]
    public function name(): string
    {
        return 'bluesky';
    }

    #[\Override]
    public function label(): string
    {
        return 'Bluesky';
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

        if ('bsky.app' !== $address->host() || 4 !== \count($segments) || 'profile' !== $segments[0] || 'post' !== $segments[2]) {
            return null;
        }

        $locator = strtolower($segments[1]).'/'.$segments[3];

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
        return $this->isLocator($locator) && str_starts_with($locator, 'did:');
    }

    #[\Override]
    public function url(string $locator): string
    {
        [$account, $key] = self::parts($locator);

        return \sprintf('https://bsky.app/profile/%s/post/%s', $account, $key);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        [$account, $key] = self::parts($locator);

        if (!str_starts_with($account, 'did:')) {
            throw new \InvalidArgumentException(\sprintf('The post "%s" of Bluesky names its account by a handle, which no frame takes.', $locator));
        }

        return \sprintf('https://embed.bsky.app/embed/%s/app.bsky.feed.post/%s', $account, $key);
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://embed.bsky.app';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return EmbedSpace::Reported;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://bsky.social/about/support/privacy-policy';
    }

    /**
     * @return array{string, string}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a post of Bluesky.', $locator));
        }

        return [$matches[1], $matches[2]];
    }
}
