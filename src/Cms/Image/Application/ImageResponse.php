<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\Image;

/**
 * An image as the CMS shows it.
 */
final readonly class ImageResponse
{
    /**
     * @param string $path where its file is kept, relative to the image storage
     */
    public function __construct(
        public string $alt,
        public string $path,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromImage(Image $image): self
    {
        return new self(
            $image->alt()->value(),
            $image->path()->value(),
            $image->timeline()->createdAt(),
        );
    }
}
