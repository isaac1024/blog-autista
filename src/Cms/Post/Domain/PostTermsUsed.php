<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The sections and tags a post is filed under or links to changed: those of
 * the version being edited and of the one last published, together; none
 * once the post is deleted. It is told at once, so whoever keeps track of
 * the terms in use is never behind the post.
 */
final readonly class PostTermsUsed extends DomainEvent implements DeliveredImmediately
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
    public function toPrimitives(): array
    {
        return ['term_ids' => $this->termIds];
    }
}
