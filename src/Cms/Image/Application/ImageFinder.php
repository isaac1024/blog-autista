<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;

/**
 * Finds an image by its id, failing when it doesn't exist.
 */
final readonly class ImageFinder
{
    public function __construct(private string $id) {}

    public function __invoke(ImageRepository $imageRepository): ImageResponse
    {
        $image = $imageRepository->find(new ImageId($this->id)) ?? throw ImageNotFound::withId($this->id);

        return ImageResponse::fromImage($image);
    }
}
