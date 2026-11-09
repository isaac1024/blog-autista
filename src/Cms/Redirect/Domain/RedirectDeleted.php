<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A redirect was deleted, so its address answers nothing any longer.
 */
final readonly class RedirectDeleted extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.redirect.deleted';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        return new static($aggregateId, $eventId, $occurredOn, new EventBody($body)->integer('revision'));
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
