<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * A received event that carries the publication revision of its aggregate,
 * so whoever listens can tell an older event from a newer one whatever order
 * they arrive in.
 */
abstract readonly class RevisedReceivedEvent extends ReceivedEvent
{
    public function __construct(
        string $aggregateId,
        private int $revision,
    ) {
        if ($revision < 1) {
            throw new \InvalidArgumentException('The event revision must be positive.');
        }

        parent::__construct($aggregateId);
    }

    public function revision(): int
    {
        return $this->revision;
    }
}
