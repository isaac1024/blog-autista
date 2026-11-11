<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

/**
 * A domain event as it travels through the queue: its name and primitives,
 * with no class of the context that recorded it.
 */
final readonly class DomainEventMessage
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public string $name,
        public string $aggregateId,
        public array $body,
    ) {}
}
