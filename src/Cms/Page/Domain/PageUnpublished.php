<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\DomainEvent;

/**
 * A page was taken off the site.
 */
final readonly class PageUnpublished extends DomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.unpublished';
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
