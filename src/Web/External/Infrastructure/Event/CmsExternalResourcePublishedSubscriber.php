<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\External\Application\ExternalResourceSaver;
use Web\External\Domain\CmsExternalResourcePublished;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Projection\Domain\Projection;

/**
 * Puts on the site each external resource the CMS publishes.
 */
final readonly class CmsExternalResourcePublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ExternalResourceRepository $resourceRepository,
        private ImageCopies $imageCopies,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsExternalResourcePublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsExternalResourcePublished) {
            $this->projection->apply('external', $event, function () use ($event): void {
                new ExternalResourceSaver($event)($this->resourceRepository, $this->imageCopies);
            });
        }
    }
}
