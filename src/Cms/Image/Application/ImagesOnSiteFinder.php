<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;

/**
 * The images on the site among those given by id, read at once, as it
 * serves them: an image never published, or taken off, is left out, as is
 * an id of no image.
 */
final readonly class ImagesOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, ImageOnSiteResponse> keyed by id
     */
    public function __invoke(ImageRepository $imageRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new ImageId($id);
            } catch (DomainError) {
                // Not the id of an image: none on the site.
            }
        }

        $images = [];

        foreach ($imageRepository->findByIds($ids) as $image) {
            $version = $image->onSite();

            if (null !== $version) {
                $images[$image->id()->value()] = ImageOnSiteResponse::fromVersion($image, $version);
            }
        }

        return $images;
    }
}
