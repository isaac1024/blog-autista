<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\RichText\LinkedContents;

/**
 * The posts, sections and tags, authors and external resources the bio of an author links to or shows in widgets changed: those of the bio being edited and of the one last published, together; none once the author is deleted. It is told at once, so
 * whoever keeps track of what is linked is never behind.
 */
final readonly class AuthorContentsLinked extends DomainEvent implements DeliveredImmediately
{
    public function __construct(
        string $aggregateId,
        private LinkedContents $linked,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.contents_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['post_ids' => $this->linked->posts(), 'term_ids' => $this->linked->terms(), 'author_ids' => $this->linked->authors(), 'external_ids' => $this->linked->externals()];
    }
}
