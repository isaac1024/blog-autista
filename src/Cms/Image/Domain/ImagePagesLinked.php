<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;

/**
 * The pages the caption of an image links to changed: those of the version
 * being edited and of the one last published, together; none once the image
 * is deleted. It is told at once, so whoever keeps track of the pages linked
 * is never behind the image.
 */
final readonly class ImagePagesLinked extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $pageIds
     */
    public function __construct(
        string $aggregateId,
        private array $pageIds,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.pages_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('page_ids'), $eventId, $occurredOn);
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['page_ids' => $this->pageIds];
    }
}
