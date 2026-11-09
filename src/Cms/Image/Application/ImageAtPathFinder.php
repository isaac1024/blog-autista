<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageRepository;

/**
 * Finds the image whose file is kept at a path, whatever its state.
 */
final readonly class ImageAtPathFinder
{
    public function __construct(private string $path) {}

    public function __invoke(ImageRepository $imageRepository): ?ImageResponse
    {
        $image = $imageRepository->findByPath(new ImagePath($this->path));

        return null === $image ? null : ImageResponse::fromImage($image);
    }
}
