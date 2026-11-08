<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;

/**
 * The pages the menus of the CMS lead to changed. Relations declare it on
 * their own, by the name the settings publish it under.
 */
final readonly class CmsSettingsPagesLinked extends DomainEvent
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
        return 'cms.settings.pages_linked';
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

    /**
     * @return list<string>
     */
    public function pageIds(): array
    {
        return $this->pageIds;
    }
}
