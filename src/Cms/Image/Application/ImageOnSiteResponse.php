<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageVersion;
use Shared\Domain\RichText\RichText;

/**
 * An image as the site serves it: the alt and caption it was published with,
 * whatever is being edited since.
 */
final readonly class ImageOnSiteResponse
{
    /**
     * @param string $path where its file is kept, relative to the image storage
     */
    public function __construct(
        public string $alt,
        public RichText $caption,
        public string $path,
    ) {}

    public static function fromVersion(Image $image, ImageVersion $version): self
    {
        return new self(
            $version->alt()->value(),
            // An image is only published with a caption.
            $version->caption() ?? RichText::empty(),
            $image->path()->value(),
        );
    }
}
