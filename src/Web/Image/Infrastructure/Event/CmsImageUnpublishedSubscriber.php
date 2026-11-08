<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Image\Application\ImageRemover;
use Web\Image\Domain\CmsImageUnpublished;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;

/**
 * Takes off the site each image the CMS unpublishes.
 */
final readonly class CmsImageUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageVariants $imageVariants,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImageUnpublished::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsImageUnpublished) {
            new ImageRemover($event)($this->imageRepository, $this->imageVariants);
        }
    }
}
