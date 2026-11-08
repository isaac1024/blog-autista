<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageFile;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Shared\Domain\Event\EventBus;

/**
 * Adds an image to the library from the file uploaded and its alternative
 * text.
 */
final readonly class ImageUploader
{
    /**
     * @param string $file path of the file uploaded
     */
    public function __construct(
        private string $id,
        private string $alt,
        private string $file,
    ) {}

    public function __invoke(ImageRepository $imageRepository, ImageStorage $imageStorage, EventBus $eventBus): void
    {
        Image::create($this->id, $this->alt)->upload(new ImageFile($this->file), $imageStorage, $imageRepository, $eventBus);
    }
}
