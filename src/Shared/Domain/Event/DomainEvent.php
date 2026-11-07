<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Something that happened to an aggregate, told to the rest of the system.
 *
 * It travels as primitives under its name, so a context that listens to it
 * declares its own event with the same name instead of depending on the
 * context that records it.
 */
abstract readonly class DomainEvent
{
    public function __construct(
        private string $aggregateId,
        private string $eventId,
        private \DateTimeImmutable $occurredOn,
    ) {}

    /**
     * Name it travels under, such as "cms.page.published".
     */
    abstract public static function eventName(): string;

    /**
     * @param array<string, mixed> $body as toPrimitives() returns it
     */
    abstract public static function fromPrimitives(
        string $aggregateId,
        array $body,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ): static;

    /**
     * What happened, as primitives.
     *
     * @return array<string, mixed>
     */
    abstract public function toPrimitives(): array;

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
