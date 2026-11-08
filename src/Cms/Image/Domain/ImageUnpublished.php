<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\DomainEvent;

/**
 * An image was taken off the site.
 */
final readonly class ImageUnpublished extends DomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        return new static($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [];
    }
}
