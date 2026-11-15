<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * Checking an external resource on the site found its address leads to it
 * again, or no longer does.
 */
final readonly class CmsExternalResourceAvailabilityChanged extends RevisedReceivedEvent
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
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static($aggregateId, $body->boolean('available'), $body->integer('revision'));
    }

    public function available(): bool
    {
        return $this->available;
    }
}
