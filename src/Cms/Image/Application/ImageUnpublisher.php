<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRedirects;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Event\EventBus;

/**
 * Takes an image off the site.
 */
final readonly class ImageUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(ImageRepository $imageRepository, EventBus $eventBus, ImageRedirects $imageRedirects): void
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        $image->unpublish($imageRedirects);
        $image->save($imageRepository, $eventBus);
    }
}
