<?php

declare(strict_types=1);

namespace Web\Shared\Domain;

use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * Applies an event only when it advances the projection of its aggregate.
 */
interface Projection
{
    public function apply(string $name, RevisedReceivedEvent $event, \Closure $apply): void;
}
