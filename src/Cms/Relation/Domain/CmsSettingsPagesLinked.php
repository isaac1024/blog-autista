<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The pages the menus of the CMS lead to changed. Relations declare it on
 * their own, by the name the settings publish it under.
 */
final readonly class CmsSettingsPagesLinked extends ReceivedEvent
{
    /**
     * @param list<string> $pageIds
     */
    public function __construct(
        string $aggregateId,
        private array $pageIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.settings.pages_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('page_ids'));
    }

    /**
     * @return list<string>
     */
    public function pageIds(): array
    {
        return $this->pageIds;
    }
}
