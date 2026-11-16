<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS took a post off the site, and so off its sitemap. The sitemap
 * declares it on its own, by the name the CMS publishes it under.
 */
final readonly class CmsPostUnpublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.unpublished';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
