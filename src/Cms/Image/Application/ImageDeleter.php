<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Image\Domain\ImageUsage;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Deletes an image off the site, with its file.
 */
final readonly class ImageDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(ImageRepository $imageRepository, ImageStorage $imageStorage, ImageUsage $imageUsage, EventBus $eventBus, Transaction $transaction): void
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        $image->delete($imageRepository, $imageStorage, $imageUsage, $eventBus, $transaction);
    }
}
