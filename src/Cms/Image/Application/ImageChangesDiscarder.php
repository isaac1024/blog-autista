<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Event\EventBus;

/**
 * Brings the version of an image being edited back to the one on the site.
 */
final readonly class ImageChangesDiscarder
{
    public function __construct(private string $id) {}

    public function __invoke(ImageRepository $imageRepository, EventBus $eventBus): void
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        $image->discardChanges();
        $image->save($imageRepository, $eventBus);
    }
}
