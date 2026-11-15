<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Posts of Threads, embedded by the frame Threads' own script would make,
 * without that script, which the site never runs. The frame reports its
 * height. Threads doesn't document it, so the facade stays when it never
 * paints.
 *
 * Its locator is the name of the account and the code of the post, such as
 * "mosseri/DCo4WiOvCB0".
 */
final readonly class Threads implements Provider
{
    private const string LOCATOR = '#^([A-Za-z0-9_.]{1,30})/([A-Za-z0-9_-]{5,20})$#';

    #[\Override]
    public function name(): string
    {
        return 'threads';
    }

    #[\Override]
    public function label(): string
    {
        return 'Threads';
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

        if (!\in_array($address->host(), ['threads.com', 'threads.net'], true) || 3 !== \count($segments) || !str_starts_with($segments[0], '@') || 'post' !== $segments[1]) {
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
        [$account, $code] = self::parts($locator);

        return \sprintf('https://www.threads.com/@%s/post/%s', $account, $code);
    }

    #[\Override]
    public function embedUrl(string $locator): string
    {
        return $this->url($locator).'/embed/';
    }

    #[\Override]
    public function frameOrigin(string $locator): string
    {
        return 'https://www.threads.com';
    }

    #[\Override]
    public function space(string $locator): EmbedSpace
    {
        return EmbedSpace::Reported;
    }

    #[\Override]
    public function privacyPolicyUrl(string $locator): string
    {
        return 'https://help.instagram.com/515230437301944';
    }

    /**
     * @return array{string, string}
     */
    private static function parts(string $locator): array
    {
        if (1 !== preg_match(self::LOCATOR, $locator, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the locator of a post of Threads.', $locator));
        }

        return [$matches[1], $matches[2]];
    }
}
