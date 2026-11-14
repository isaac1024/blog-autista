<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The authors a post of the CMS is signed by changed. Relations declare it on their own, by the name the
 * posts publish it under.
 */
final readonly class CmsPostAuthorsUsed extends ReceivedEvent
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
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('author_ids'));
    }

    /**
     * @return list<string>
     */
    public function authorIds(): array
    {
        return $this->authorIds;
    }
}
