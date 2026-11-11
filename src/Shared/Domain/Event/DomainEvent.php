<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Something that happened to an aggregate, told to the rest of the system.
 *
 * It travels as primitives under its name, so a context that listens to it
 * declares its own ReceivedEvent with the same name instead of depending on
 * the context that records it.
 */
abstract readonly class DomainEvent
{
    public function __construct(private string $aggregateId) {}

    /**
     * Name it travels under, such as "cms.page.published".
     */
    abstract public static function eventName(): string;

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
}
