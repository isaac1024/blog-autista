<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;

/**
 * The images a page embeds changed: those of the version being edited and of
 * the one last published, together; none once the page is deleted. It is
 * told at once, so whoever keeps track of the images in use is never behind
 * the page.
 */
final readonly class PageImagesUsed extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $imageIds
     */
    public function __construct(
        string $aggregateId,
        private array $imageIds,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.images_used';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('image_ids'), $eventId, $occurredOn);
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['image_ids' => $this->imageIds];
    }
}
