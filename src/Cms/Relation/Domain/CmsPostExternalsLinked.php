<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The external resources a post of the CMS links to changed. Relations
 * declare it on their own, by the name the posts publish it under.
 */
final readonly class CmsPostExternalsLinked extends ReceivedEvent
{
    /**
     * @param list<string> $externalIds
     */
    public function __construct(
        string $aggregateId,
        private array $externalIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.externals_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('external_ids'));
    }

    /**
     * @return list<string>
     */
    public function externalIds(): array
    {
        return $this->externalIds;
    }
}
