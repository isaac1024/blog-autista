<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;

/**
 * The CMS put an external resource on the site, or replaced its version
 * there, with all the site needs to paint it, and whether it is available.
 * The site declares it on its own, by the name the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsExternalResourcePublished extends RevisedReceivedEvent
{
    /**
     * @param array<array-key, mixed> $details
     */
    public function __construct(
        string $aggregateId,
        private ResourceKind $kind,
        private ?string $provider,
        private ?string $locator,
        private string $url,
        private string $title,
        private ?string $summary,
        private string $language,
        private array $details,
        private ?string $image,
        private ?ImageShape $imageShape,
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
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);
        $shape = $body->nullableString('image_shape');

        return new static(
            $aggregateId,
            ResourceKind::from($body->string('kind')),
            $body->nullableString('provider'),
            $body->nullableString('locator'),
            $body->string('url'),
            $body->string('title'),
            $body->nullableString('summary'),
            $body->string('language'),
            $body->array('details'),
            $body->nullableString('image'),
            null === $shape ? null : ImageShape::from($shape),
            $body->boolean('available'),
            $body->integer('revision'),
        );
    }

    public function kind(): ResourceKind
    {
        return $this->kind;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function locator(): ?string
    {
        return $this->locator;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function language(): string
    {
        return $this->language;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * Where the CMS keeps its image, relative to its storage, if it has one.
     */
    public function image(): ?string
    {
        return $this->image;
    }

    public function imageShape(): ?ImageShape
    {
        return $this->imageShape;
    }

    public function available(): bool
    {
        return $this->available;
    }
}
