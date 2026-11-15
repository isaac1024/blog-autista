<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * Checking a published resource found its address leads to it again, or
 * no longer does: the site paints it again, or stops, at once, with no new
 * version to publish.
 */
final readonly class ExternalResourceAvailabilityChanged extends RevisedDomainEvent
{
    public function __construct(
        string $aggregateId,
        private bool $available,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.external.availability_changed';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['available' => $this->available, 'revision' => $this->revision()];
    }
}
