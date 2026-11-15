<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The posts, sections and tags, authors and external resources a page of the CMS links to
 * changed. Relations declare it on their own, by the name the pages
 * publish it under.
 */
final readonly class CmsPageContentsLinked extends ReceivedEvent
{
    /**
     * @param list<string> $postIds
     * @param list<string> $termIds
     * @param list<string> $authorIds
     * @param list<string> $externalIds
     */
    public function __construct(
        string $aggregateId,
        private array $postIds,
        private array $termIds,
        private array $authorIds,
        private array $externalIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.contents_linked';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static($aggregateId, $body->stringList('post_ids'), $body->stringList('term_ids'), $body->stringList('author_ids'), $body->stringList('external_ids'));
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

    /**
     * @return list<string>
     */
    public function externalIds(): array
    {
        return $this->externalIds;
    }
}
