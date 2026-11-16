<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS published an image, or published it again, which the sitemap names for the posts that open
 * with it: their files changed then. The sitemap declares it on its own, by
 * the name the CMS publishes it under.
 */
final readonly class CmsImagePublished extends RevisedReceivedEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->integer('revision'));
    }
}
