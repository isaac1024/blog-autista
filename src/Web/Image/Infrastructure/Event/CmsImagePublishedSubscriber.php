<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Image\Application\ImageSaver;
use Web\Image\Domain\CmsImagePublished;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;

/**
 * Puts on the site each image the CMS publishes.
 */
final readonly class CmsImagePublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageVariants $imageVariants,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImagePublished::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsImagePublished) {
            new ImageSaver($event)($this->imageRepository, $this->imageVariants);
        }
    }
}
