<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The external resources a post links to or shows in widgets changed: those
 * of the version being edited and of the one last published, together;
 * none once the post is deleted. It is told at once, so whoever keeps track
 * of the resources in use is never behind the post.
 */
final readonly class PostExternalsLinked extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $externalIds
     */
    public function __construct(
        string $aggregateId,
        private array $externalIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.externals_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['external_ids' => $this->externalIds];
    }
}
