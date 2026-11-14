<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took a term off the site.
 */
final readonly class CmsTermUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.term.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
