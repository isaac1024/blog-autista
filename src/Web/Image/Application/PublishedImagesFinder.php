<?php

declare(strict_types=1);

namespace Web\Image\Application;

use Shared\Domain\DomainError;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;

/**
 * The images on the site among those a page embeds: an image the CMS took
 * off, or never put on, is left out, so the page leaves it out too.
 */
final readonly class PublishedImagesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, ImageResponse> keyed by id
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
            $images[$image->id()->value()] = ImageResponse::fromImage($image);
        }

        return $images;
    }
}
