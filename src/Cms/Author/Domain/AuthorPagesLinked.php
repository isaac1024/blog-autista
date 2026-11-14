<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The pages the bio of an author links to changed: those of the version being
 * edited and of the one last published, together; none once the author is
 * deleted. It is told at once, so whoever keeps track of what is in use is
 * never behind the author.
 */
final readonly class AuthorPagesLinked extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $pageIds
     */
    public function __construct(
        string $aggregateId,
        private array $pageIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.pages_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['page_ids' => $this->pageIds];
    }
}
