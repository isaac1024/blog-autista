<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\Provider;
use Shared\Domain\External\Providers;
use Shared\Domain\External\ResourceKind;

/**
 * What an address is, as far as it tells by itself: a resource a provider
 * embeds, with its locator, a study, or something else, an article or a
 * document, which only reading it tells apart.
 */
final readonly class ResourceSource
{
    private function __construct(
        private ?ResourceKind $kind,
        private ?Provider $provider,
        private ?string $locator,
    ) {}

    public static function fromUrl(ResourceUrl $url, Providers $providers): self
    {
        $located = $providers->locate($url->address());

        if (null !== $located) {
            return new self($located[0]->kind(), $located[0], $located[1]);
        }

        if (null !== StudyReference::fromAddress($url->address())) {
            return new self(ResourceKind::Study, null, null);
        }

        return new self(null, null, null);
    }

    public function provider(): ?Provider
    {
        return $this->provider;
    }

    public function locator(): ?string
    {
        return $this->locator;
    }

    /**
     * Whether a resource of the kind, by the provider named, if any, may
     * have this address: an embedded resource keeps its provider, a study
     * stays a study, and an article or a document never takes an address of
     * either.
     */
    public function fits(ResourceKind $kind, ?string $provider): bool
    {
        if (null === $this->kind) {
            return \in_array($kind, [ResourceKind::Article, ResourceKind::Document], true);
        }

        return $kind === $this->kind && $provider === $this->provider?->name();
    }
}
