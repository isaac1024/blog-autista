<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Hands each queued event to the subscribers that declare an event with its
 * name, rebuilt as their own event class.
 */
#[AsMessageHandler]
final readonly class DomainEventMessageHandler
{
    /**
     * @param iterable<DomainEventSubscriber> $subscribers
     */
    public function __construct(
        #[AutowireIterator(DomainEventSubscriber::class)]
        private iterable $subscribers,
    ) {}

    public function __invoke(DomainEventMessage $message): void
    {
        foreach ($this->subscribers as $subscriber) {
            foreach ($subscriber::subscribedTo() as $eventClass) {
                if ($eventClass::eventName() === $message->name) {
                    $subscriber->handle($eventClass::fromPrimitives($message->aggregateId, $message->body));
                }
            }
        }
    }
}
