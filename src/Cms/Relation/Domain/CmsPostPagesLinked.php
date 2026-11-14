<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The pages the content of a post of the CMS links to changed. Relations declare it on their own, by the name the
 * posts publish it under.
 */
final readonly class CmsPostPagesLinked extends ReceivedEvent
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
        return 'cms.post.pages_linked';
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
