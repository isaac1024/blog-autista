<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\Event\DomainEvent;

/**
 * The CMS took a page off the site.
 */
final readonly class CmsPageUnpublished extends DomainEvent
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
