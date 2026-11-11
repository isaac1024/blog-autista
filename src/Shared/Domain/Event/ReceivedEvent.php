<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Something that happened to an aggregate of another context or module, as
 * the one that listens to it declares it: rebuilt from the primitives it
 * travels as, under the name it was told with, never told again. Of what
 * travels with it, it keeps only what the listener needs.
 */
abstract readonly class ReceivedEvent
{
    public function __construct(private string $aggregateId) {}

    /**
     * Name it travels under, as the event told declares it.
     */
    abstract public static function eventName(): string;

    /**
     * @param array<string, mixed> $body as the event told turned it into primitives
     */
    abstract public static function fromPrimitives(string $aggregateId, array $body): static;

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }
}
