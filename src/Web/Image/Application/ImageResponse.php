<?php

declare(strict_types=1);

namespace Web\Image\Application;

use Shared\Domain\RichText\RichText;
use Web\Image\Domain\Image;

/**
 * An image as the site shows it.
 */
final readonly class ImageResponse
{
    /**
     * @param string $path where the CMS keeps its file, which names its copies
     */
    public function __construct(
        public string $alt,
        public RichText $caption,
        public string $path,
    ) {}

    public static function fromImage(Image $image): self
    {
        return new self($image->alt(), $image->caption(), $image->path());
    }
}
