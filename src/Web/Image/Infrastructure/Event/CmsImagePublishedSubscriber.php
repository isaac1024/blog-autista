<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Image\Application\ImageSaver;
use Web\Image\Domain\CmsImagePublished;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;
use Web\Projection\Domain\Projection;

/**
 * Puts on the site each image the CMS publishes.
 */
final readonly class CmsImagePublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageVariants $imageVariants,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImagePublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsImagePublished) {
            $this->projection->apply('image', $event, function () use ($event): void {
                new ImageSaver($event)($this->imageRepository, $this->imageVariants);
            });
        }
    }
}
