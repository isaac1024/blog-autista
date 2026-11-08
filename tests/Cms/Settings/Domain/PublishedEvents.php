<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Domain;

use PHPUnit\Framework\MockObject\Stub;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;

/**
 * Collects the events the settings tell, as name, aggregate id and body, in
 * the order they are told, so a test compares them whole.
 */
trait PublishedEvents
{
    /**
     * @param list<array{string, string, array<string, mixed>}> $told filled with each event told
     */
    private function eventBusCollecting(array &$told): EventBus&Stub
    {
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback(static function (DomainEvent ...$events) use (&$told): void {
            foreach ($events as $event) {
                $told[] = [$event::eventName(), $event->aggregateId(), $event->toPrimitives()];
            }
        });

        return $eventBus;
    }
}
