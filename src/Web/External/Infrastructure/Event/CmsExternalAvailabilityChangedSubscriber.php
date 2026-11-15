<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\External\Application\AvailabilityChanger;
use Web\External\Domain\CmsExternalResourceAvailabilityChanged;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Projection\Domain\Projection;

/**
 * Paints, or stops painting, each external resource whose availability the CMS checked.
 */
final readonly class CmsExternalAvailabilityChangedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ExternalResourceRepository $resourceRepository,
        private ImageCopies $imageCopies,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsExternalResourceAvailabilityChanged::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsExternalResourceAvailabilityChanged) {
            $this->projection->apply('external', $event, function () use ($event): void {
                new AvailabilityChanger($event)($this->resourceRepository, $this->imageCopies);
            });
        }
    }
}
