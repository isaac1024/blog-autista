<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The posts, sections and tags, and authors a author of the CMS links to
 * changed. Relations declare it on their own, by the name the authors
 * publish it under.
 */
final readonly class CmsAuthorContentsLinked extends ReceivedEvent
{
    /**
     * @param list<string> $postIds
     * @param list<string> $termIds
     * @param list<string> $authorIds
     */
    public function __construct(
        string $aggregateId,
        private array $postIds,
        private array $termIds,
        private array $authorIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.contents_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static($aggregateId, $body->stringList('post_ids'), $body->stringList('term_ids'), $body->stringList('author_ids'));
    }

    /**
     * @return list<string>
     */
    public function postIds(): array
    {
        return $this->postIds;
    }

    /**
     * @return list<string>
     */
    public function termIds(): array
    {
        return $this->termIds;
    }

    /**
     * @return list<string>
     */
    public function authorIds(): array
    {
        return $this->authorIds;
    }
}
