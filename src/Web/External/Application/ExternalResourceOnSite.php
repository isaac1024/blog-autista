<?php

declare(strict_types=1);

namespace Web\External\Application;

use Web\External\Domain\ExternalResource;

/**
 * Read model of an external resource on the site handed to the adapters,
 * so they don't touch the entity.
 *
 * It carries everything the site paints of it, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ExternalResourceOnSite
{
    /**
     * @param string                  $kind       one of the ResourceKind values
     * @param array<array-key, mixed> $details
     * @param ?string                 $image      where the CMS keeps its image, relative to its storage
     * @param ?string                 $imageShape one of the ImageShape values
     */
    public function __construct(
        public string $kind,
        public ?string $provider,
        public ?string $locator,
        public string $url,
        public string $title,
        public ?string $summary,
        public string $language,
        public array $details,
        public ?string $image,
        public ?string $imageShape,
    ) {}

    public static function fromResource(ExternalResource $resource): self
    {
        return new self(
            $resource->kind()->value,
            $resource->provider(),
            $resource->locator(),
            $resource->url(),
            $resource->title(),
            $resource->summary(),
            $resource->language(),
            $resource->details(),
            $resource->image(),
            $resource->imageShape()?->value,
        );
    }
}
