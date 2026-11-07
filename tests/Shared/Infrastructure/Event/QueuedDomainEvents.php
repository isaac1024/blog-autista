<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Event;

use Shared\Infrastructure\Event\DomainEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Reads the domain events queued on the async transport, which tests keep in
 * memory, for kernel tests that publish them.
 */
trait QueuedDomainEvents
{
    /**
     * @return list<DomainEventMessage>
     */
    private function queuedEvents(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_map(
            static function (Envelope $envelope): DomainEventMessage {
                $message = $envelope->getMessage();
                self::assertInstanceOf(DomainEventMessage::class, $message);

                return $message;
            },
            [...$transport->getSent()],
        ));
    }
}
