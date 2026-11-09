<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * The CMS deleted a redirect.
 */
final readonly class CmsRedirectDeleted extends RevisedDomainEvent
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
