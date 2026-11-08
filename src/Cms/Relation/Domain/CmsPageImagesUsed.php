<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;

/**
 * The images a page of the CMS embeds changed. Relations declare it on their
 * own, by the name the pages publish it under.
 */
final readonly class CmsPageImagesUsed extends DomainEvent
{
    /**
     * @param list<string> $imageIds
     */
    public function __construct(
        string $aggregateId,
        private array $imageIds,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.images_used';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('image_ids'), $eventId, $occurredOn);
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['image_ids' => $this->imageIds];
    }

    /**
     * @return list<string>
     */
    public function imageIds(): array
    {
        return $this->imageIds;
    }
}
