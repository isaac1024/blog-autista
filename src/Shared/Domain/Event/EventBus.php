<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Outbound port that tells the rest of the system what happened.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
