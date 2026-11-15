<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took an external resource off the site.
 */
final readonly class CmsExternalResourceUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.external.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
