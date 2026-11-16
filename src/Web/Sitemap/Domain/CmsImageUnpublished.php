<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took an image off the site, which the sitemap names for the posts that open
 * with it: their files changed then. The sitemap declares it on its own, by
 * the name the CMS publishes it under.
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
