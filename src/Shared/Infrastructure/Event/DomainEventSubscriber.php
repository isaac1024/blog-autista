<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Inbound adapter that reacts to domain events, of its own context or of
 * another one, by the events it declares.
 */
#[AutoconfigureTag]
interface DomainEventSubscriber
{
    /**
     * @return list<class-string<ReceivedEvent>>
     */
    public static function subscribedTo(): array;

    public function handle(ReceivedEvent $event): void;
}
