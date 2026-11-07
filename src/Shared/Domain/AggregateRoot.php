<?php

declare(strict_types=1);

namespace Shared\Domain;

use Shared\Domain\Event\DomainEvent;

/**
 * Base of aggregates that record what happens to them, and tell it to the
 * rest of the system themselves once they are saved, so no event leaves
 * them any other way.
 */
abstract class AggregateRoot
{
    /**
     * Not persisted; it only holds what happened since the aggregate was loaded.
     *
     * @var list<DomainEvent>
     */
    private array $domainEvents = [];

    /**
     * Hands over the recorded events, and forgets them, so none is published twice.
     *
     * @return list<DomainEvent>
     */
    final protected function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    final protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
