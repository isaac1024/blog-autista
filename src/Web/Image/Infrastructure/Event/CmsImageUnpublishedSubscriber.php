<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Image\Application\ImageRemover;
use Web\Image\Domain\CmsImageUnpublished;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;
use Web\Projection\Domain\Projection;

/**
 * Takes off the site each image the CMS unpublishes.
 */
final readonly class CmsImageUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageVariants $imageVariants,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImageUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsImageUnpublished) {
            $this->projection->apply('image', $event, function () use ($event): void {
                new ImageRemover($event)($this->imageRepository, $this->imageVariants);
            });
        }
    }
}
