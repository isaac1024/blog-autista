<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The posts a post of the CMS links to changed. Relations declare it on their own, by the name the
 * posts publish it under.
 */
final readonly class CmsPostPostsLinked extends ReceivedEvent
{
    /**
     * @param list<string> $postIds
     */
    public function __construct(
        string $aggregateId,
        private array $postIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.posts_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('post_ids'));
    }

    /**
     * @return list<string>
     */
    public function postIds(): array
    {
        return $this->postIds;
    }
}
