<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\RichText\LinkedContents;

/**
 * The posts, sections and tags, authors and external resources a page links to or shows in widgets changed: those of the version being edited and of the one last published, together; none once the page is deleted. It is told at once, so
 * whoever keeps track of what is linked is never behind.
 */
final readonly class PageContentsLinked extends DomainEvent implements DeliveredImmediately
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
        return 'cms.page.contents_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['post_ids' => $this->linked->posts(), 'term_ids' => $this->linked->terms(), 'author_ids' => $this->linked->authors(), 'external_ids' => $this->linked->externals()];
    }
}
