<?php

declare(strict_types=1);

namespace Web\Image\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * The CMS took an image off the site.
 */
final readonly class CmsImageUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.unpublished';
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
