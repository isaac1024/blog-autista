<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An external resource went on the site, or the version there was replaced,
 * with all the site needs to paint it, and whether it is available.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ExternalResourcePublished extends RevisedDomainEvent
{
    /**
     * @param ?string                             $provider   the name of the provider that embeds it, if any
     * @param ?string                             $locator    how its provider names it
     * @param array<string, null|bool|int|string> $details
     * @param ?string                             $image      where the CMS keeps its image, relative to its storage
     * @param ?string                             $imageShape the shape of its image, when it has one
     */
    public function __construct(
        string $aggregateId,
        private string $kind,
        private ?string $provider,
        private ?string $locator,
        private string $url,
        private string $title,
        private ?string $summary,
        private string $language,
        private array $details,
        private ?string $image,
        private ?string $imageShape,
        private bool $available,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.external.published';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'kind' => $this->kind,
            'provider' => $this->provider,
            'locator' => $this->locator,
            'url' => $this->url,
            'title' => $this->title,
            'summary' => $this->summary,
            'language' => $this->language,
            'details' => $this->details,
            'image' => $this->image,
            'image_shape' => $this->imageShape,
            'available' => $this->available,
            'revision' => $this->revision(),
        ];
    }
}
