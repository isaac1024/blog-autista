<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResource;
use Shared\Domain\External\Providers;

/**
 * An external resource as the site paints it: the version on the site of a
 * resource published and available.
 *
 * It carries everything the site paints of it, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ExternalResourceOnSiteResponse
{
    /**
     * @param string                              $kind       one of the ResourceKind values
     * @param array<string, null|bool|int|string> $details
     * @param ?string                             $image      where the image is kept, relative to the image storage
     * @param ?string                             $imageShape one of the ImageShape values
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

    /**
     * Of a published resource, whose version on the site is the one checked.
     */
    public static function fromResource(ExternalResource $resource): self
    {
        $version = $resource->checked();

        return new self(
            $resource->kind()->value,
            $resource->provider(),
            $version->locator(),
            $version->url()->value(),
            $version->title()->value(),
            $version->summary(),
            $version->language()->value,
            $version->details()->toArray(),
            $version->image()?->value(),
            $version->imageShape($resource->kind(), null === $resource->provider() ? null : new Providers()->named($resource->provider()))?->value,
        );
    }
}
