<?php

declare(strict_types=1);

namespace Web\Image\Application;

use Web\Image\Domain\CmsImageUnpublished;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;

/**
 * Takes off the site an image the CMS unpublished, with its copies. An image
 * already gone stays gone, so the same event can be taken twice.
 */
final readonly class ImageRemover
{
    public function __construct(private CmsImageUnpublished $event) {}

    public function __invoke(ImageRepository $imageRepository, ImageVariants $imageVariants): void
    {
        $imageRepository->find(new ImageId($this->event->aggregateId()))?->unpublish($this->event, $imageRepository, $imageVariants);
    }
}
