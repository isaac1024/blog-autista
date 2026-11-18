<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Saves what an editor writes about an image: its alternative text and its
 * caption.
 */
final readonly class ImageUpdater
{
    /**
     * @param array<array-key, mixed> $caption in the stored rich text format
     */
    public function __construct(
        private string $id,
        private string $alt,
        private array $caption,
    ) {}

    public function __invoke(ImageRepository $imageRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        $image->edit($this->alt, $this->caption);
        $image->save($imageRepository, $eventBus, $transaction);
    }
}
