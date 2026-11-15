<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Posts of X, embedded by the frame X's own script would make, without that
 * script, which the site never runs. The frame reports its height. X
 * doesn't document it, so the facade stays when it never paints.
 *
 * Its locator is the name of the account and the id of the post, such as
 * "jack/20".
 */
final readonly class XPlatform implements Provider
{
    private const string LOCATOR = '#^([A-Za-z0-9_]{1,15})/([0-9]{1,25})$#';

    #[\Override]
    public function name(): string
    {
        return 'x';
    }

    #[\Override]
    public function label(): string
    {
        return 'X';
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

        if (!\in_array($address->host(), ['x.com', 'twitter.com', 'mobile.twitter.com', 'mobile.x.com'], true) || \count($segments) < 3 || 'status' !== $segments[1]) {
            return null;
        }

        // A post may be addressed by one of its photos or videos, after its id.
        $locator = $segments[0].'/'.$segments[2];

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

        return \sprintf('https://x.com/%s/status/%s', $account, $id);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        return \sprintf('https://platform.twitter.com/embed/Tweet.html?id=%s&dnt=true&lang=es', self::parts($locator)[1]);
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://platform.twitter.com';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return EmbedSpace::Reported;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://x.com/es/privacy';
    }

    /**
     * @return array{string, string}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a post of X.', $locator));
        }

        return [$matches[1], $matches[2]];
    }
}
