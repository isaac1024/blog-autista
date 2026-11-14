<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The sections and tags a post of the CMS is filed under changed. Relations declare it on their own, by the name the
 * posts publish it under.
 */
final readonly class CmsPostTermsUsed extends ReceivedEvent
{
    /**
     * @param list<string> $termIds
     */
    public function __construct(
        string $aggregateId,
        private array $termIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.terms_used';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('term_ids'));
    }

    /**
     * @return list<string>
     */
    public function termIds(): array
    {
        return $this->termIds;
    }
}
