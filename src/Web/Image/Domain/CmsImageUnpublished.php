<?php

declare(strict_types=1);

namespace Web\Image\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took an image off the site.
 */
final readonly class CmsImageUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
