<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Image;

use Cms\Image\Application\ImagesFinder;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Domain\ImageId;
use Cms\Post\Domain\PostImages;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the image library of the CMS whether an image
 * is on the site, as it is saved.
 */
#[AsAlias(PostImages::class)]
final readonly class CmsPostImages implements PostImages
{
    public function __construct(private ImageRepository $imageRepository) {}

    #[\Override]
    public function exists(ImageId $id): bool
    {
        return [] !== new ImagesFinder([$id->value()])($this->imageRepository);
    }

    #[\Override]
    public function isOnSite(ImageId $id): bool
    {
        return [] !== new ImagesOnSiteFinder([$id->value()])($this->imageRepository);
    }
}
