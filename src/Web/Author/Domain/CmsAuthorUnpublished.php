<?php

declare(strict_types=1);

namespace Web\Author\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took an author off the site.
 */
final readonly class CmsAuthorUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
