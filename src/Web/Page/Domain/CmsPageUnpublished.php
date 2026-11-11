<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took a page off the site.
 */
final readonly class CmsPageUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
