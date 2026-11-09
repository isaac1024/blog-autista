<?php

declare(strict_types=1);

namespace Web\Projection\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * Applies an event only when it advances the projection of its aggregate.
 */
interface Projection
{
    public function apply(string $name, RevisedDomainEvent $event, \Closure $apply): void;
}
