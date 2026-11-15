<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\External\Application\ExternalResourceRemover;
use Web\External\Domain\CmsExternalResourceUnpublished;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Projection\Domain\Projection;

/**
 * Takes off the site each external resource the CMS unpublishes.
 */
final readonly class CmsExternalResourceUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ExternalResourceRepository $resourceRepository,
        private ImageCopies $imageCopies,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsExternalResourceUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsExternalResourceUnpublished) {
            $this->projection->apply('external', $event, function () use ($event): void {
                new ExternalResourceRemover($event)($this->resourceRepository, $this->imageCopies);
            });
        }
    }
}
