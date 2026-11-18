<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Puts on the site the version of an image last saved.
 */
final readonly class ImagePublisher
{
    public function __construct(private string $id) {}

    public function __invoke(ImageRepository $imageRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        $image->publish();
        $image->save($imageRepository, $eventBus, $transaction);
    }
}
