<?php

declare(strict_types=1);

namespace Shared\Domain;

use Shared\Domain\Event\DomainEvent;

/**
 * Base of aggregates that record what happens to them, so the application
 * publishes it once the aggregate is saved.
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
    final public function pullDomainEvents(): array
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
