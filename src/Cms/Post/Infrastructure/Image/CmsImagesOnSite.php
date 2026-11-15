<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Image;

use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Domain\ImageId;
use Cms\Post\Domain\ImagesOnSite;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the image library of the CMS whether an image
 * is on the site, as it is saved.
 */
#[AsAlias(ImagesOnSite::class)]
final readonly class CmsImagesOnSite implements ImagesOnSite
{
    public function __construct(private ImageRepository $imageRepository) {}

    #[\Override]
    public function isOnSite(ImageId $id): bool
    {
        return [] !== new ImagesOnSiteFinder([$id->value()])($this->imageRepository);
    }
}
