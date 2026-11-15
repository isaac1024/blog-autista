<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\External\Providers;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Turns an external resource into the component that paints it where a
 * text shows it: a video, a post or an audio behind the facade that loads
 * it from its provider once the reader agrees, anything else as a card of
 * related content that leads to it. The site and the preview of the CMS
 * both paint it this way, each from what it has.
 *
 * @phpstan-type ExternalView array{kind: string, provider: ?string, locator: ?string, url: string, title: string, summary: ?string, language: string, details: array<array-key, mixed>, image: ?array{src: string, srcset: string, width: int, height: int}, cookies: ?string}
 */
final readonly class ExternalComponents
{
    /**
     * @param ExternalView $external
     */
    public static function component(array $external): PageComponent
    {
        $kind = ResourceKind::from($external['kind']);
        $provider = null === $external['provider'] ? null : new Providers()->named($external['provider']);
        $locator = $external['locator'];

        if (!$kind->isEmbedded() || null === $provider || null === $locator || !$provider->isEmbeddable($locator)) {
            return new PageComponent('Shared:ContentExternal', [
                'kind' => $kind->value,
                'url' => $external['url'],
                'title' => $external['title'],
                'summary' => $external['summary'],
                'language' => $external['language'],
                'details' => $external['details'],
                'image' => $external['image'],
            ]);
        }

        return new PageComponent('Shared:ExternalEmbed', [
            'kind' => $kind->value,
            'provider' => $provider->name(),
            'providerLabel' => $provider->label(),
            'url' => $external['url'],
            'title' => $external['title'],
            'language' => $external['language'],
            'embedUrl' => $provider->embedUrl($locator),
            'frameOrigin' => $provider->frameOrigin($locator),
            'space' => $provider->space($locator)->value,
            'privacyUrl' => $provider->privacyPolicyUrl($locator),
            'cookiesUrl' => $external['cookies'],
            'details' => $external['details'],
            'image' => $external['image'],
        ]);
    }

    /**
     * The origin the page lets frame the resource, null for one it paints
     * as a card.
     *
     * @param ExternalView $external
     */
    public static function frameOrigin(array $external): ?string
    {
        $component = self::component($external);

        return 'Shared:ExternalEmbed' === $component->name && \is_string($component->props['frameOrigin'] ?? null) ? $component->props['frameOrigin'] : null;
    }
}
