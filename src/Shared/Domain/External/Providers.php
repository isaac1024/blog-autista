<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * Every provider whose resources the site embeds, so the CMS and the site
 * always agree on which there are and on how each one embeds.
 */
final readonly class Providers
{
    /**
     * @var list<Provider>
     */
    private array $providers;

    public function __construct()
    {
        // Mastodon last: any server can host it, so its addresses only stand
        // when no provider of a known host takes them.
        $this->providers = [new YouTube(), new TikTok(), new Bluesky(), new XPlatform(), new Threads(), new Spotify(), new Mastodon()];
    }

    /**
     * The provider of the name, as name() gives it.
     *
     * @throws \InvalidArgumentException when no provider has it
     */
    public function named(string $name): Provider
    {
        foreach ($this->providers as $provider) {
            if ($name === $provider->name()) {
                return $provider;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No provider is named "%s".', $name));
    }

    /**
     * The provider that embeds the resource the address names, and its
     * locator, or null when no provider does.
     *
     * @return null|array{Provider, string}
     */
    public function locate(WebAddress $address): ?array
    {
        foreach ($this->providers as $provider) {
            $locator = self::locator($provider, $address);

            if (null !== $locator) {
                return [$provider, $locator];
            }
        }

        return null;
    }

    /**
     * How the provider names what the address does, asked as any provider.
     */
    private static function locator(Provider $provider, WebAddress $address): ?string
    {
        return $provider->locate($address);
    }
}
