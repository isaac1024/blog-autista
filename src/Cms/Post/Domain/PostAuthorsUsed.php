<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The authors a post is signed by or links to changed: those of the version
 * being edited and of the one last published, together; none once the post
 * is deleted. It is told at once, so whoever keeps track of the authors in
 * use is never behind the post.
 */
final readonly class PostAuthorsUsed extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $authorIds
     */
    public function __construct(
        string $aggregateId,
        private array $authorIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.authors_used';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['author_ids' => $this->authorIds];
    }
}
