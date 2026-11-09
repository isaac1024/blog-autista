<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Queues domain events on Messenger, which delivers them to their
 * subscribers asynchronously.
 */
#[AsAlias(EventBus::class)]
final readonly class MessengerEventBus implements EventBus
{
    public function __construct(private MessageBusInterface $messageBus) {}

    #[\Override]
    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->messageBus->dispatch(
                new DomainEventMessage(
                    $event::eventName(),
                    $event->eventId(),
                    $event->aggregateId(),
                    $event->occurredOn()->format('Y-m-d\TH:i:s.uP'),
                    $event->toPrimitives(),
                ),
                // Events to be told at once skip the queue: they are handled
                // within the change that records them.
                $event instanceof DeliveredImmediately ? [new TransportNamesStamp(['sync'])] : [],
            );
        }
    }
}
