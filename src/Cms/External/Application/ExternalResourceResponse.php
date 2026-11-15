<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ResourceStatus;

/**
 * Read model of an external resource handed to the adapters, so they don't
 * touch the aggregate.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ExternalResourceResponse
{
    /**
     * @param string                              $kind                  one of the ResourceKind values
     * @param ?string                             $provider              the name of the provider that embeds it, if any
     * @param ?string                             $summary               null when it has none
     * @param string                              $language              one of the Language values
     * @param array<string, null|bool|int|string> $details
     * @param ?string                             $image                 where the image being edited is kept, relative to the image storage; null without one
     * @param ?string                             $imageShape            one of the ImageShape values, null for a resource without images
     * @param string                              $status                one of the ResourceStatus values
     * @param bool                                $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param bool                                $available             whether its address still leads to it, as last checked
     * @param string                              $checkedUrl            the address whose availability it keeps
     * @param bool                                $hasSomethingToPublish whether publishing would change the site
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $provider,
        public string $url,
        public string $title,
        public ?string $summary,
        public string $language,
        public array $details,
        public ?string $image,
        public ?string $imageShape,
        public string $status,
        public bool $changed,
        public bool $available,
        public ?\DateTimeImmutable $checkedAt,
        public string $checkedUrl,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
    ) {}

    public static function fromResource(ExternalResource $resource): self
    {
        $version = $resource->version();

        return new self(
            $resource->id()->value(),
            $resource->kind()->value,
            $resource->provider(),
            $version->url()->value(),
            $version->title()->value(),
            $version->summary(),
            $version->language()->value,
            $version->details()->toArray(),
            $version->image()?->value(),
            $resource->imageShape()?->value,
            $resource->status()->value,
            $resource->hasUnpublishedChanges(),
            Availability::Available === $resource->availability(),
            $resource->checkedAt(),
            $resource->checked()->url()->value(),
            $resource->timeline()->createdAt(),
            $resource->timeline()->updatedAt(),
            $resource->hasSomethingToPublish(),
            $resource->canBeUnpublished(),
            $resource->canBeDeleted(),
        );
    }

    public function isPublished(): bool
    {
        return ResourceStatus::Published->value === $this->status;
    }
}
