<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The posts a post links to or shows in widgets changed: those of the
 * version being edited and of the one last published, together; none once
 * the post is deleted. It is told at once, so whoever keeps track of the
 * posts linked is never behind the post.
 */
final readonly class PostPostsLinked extends DomainEvent implements DeliveredImmediately
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
    public function toPrimitives(): array
    {
        return ['post_ids' => $this->postIds];
    }
}
