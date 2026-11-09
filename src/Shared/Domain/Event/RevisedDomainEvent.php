<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * An event that carries the publication revision of its aggregate, which
 * grows with each publication and unpublication, so whoever listens can tell
 * an older event from a newer one whatever order they arrive in.
 */
abstract readonly class RevisedDomainEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        string $eventId,
        \DateTimeImmutable $occurredOn,
        private int $revision,
    ) {
        if ($revision < 1) {
            throw new \InvalidArgumentException('The event revision must be positive.');
        }

        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public function revision(): int
    {
        return $this->revision;
    }
}
