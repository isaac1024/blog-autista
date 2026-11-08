<?php

declare(strict_types=1);

namespace Web\Image\Application;

use Web\Image\Domain\CmsImagePublished;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;

/**
 * Puts on the site the version of an image the CMS published, in place of
 * the one there, if any, with the copies the site serves.
 */
final readonly class ImageSaver
{
    public function __construct(private CmsImagePublished $event) {}

    public function __invoke(ImageRepository $imageRepository, ImageVariants $imageVariants): void
    {
        $image = $imageRepository->find(new ImageId($this->event->aggregateId()));

        if (null === $image) {
            $image = Image::publish($this->event);
        } else {
            $image->republish($this->event);
        }

        $image->save($imageRepository, $imageVariants);
    }
}
