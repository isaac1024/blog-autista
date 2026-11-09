<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;

/**
 * Finds the images among those given by id, in one go and in the order given,
 * each once: an id of no image, or not an id at all, is left out.
 */
final readonly class ImagesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<ImageResponse>
     */
    public function __invoke(ImageRepository $imageRepository): array
    {
        $ids = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $ids[] = new ImageId($id);
            } catch (DomainError) {
                // Not the id of an image: none to find.
            }
        }

        $found = [];

        foreach ($imageRepository->findByIds($ids) as $image) {
            $found[$image->id()->value()] = ImageResponse::fromImage($image);
        }

        // The repository answers in its own order.
        return array_values(array_filter(array_map(
            static fn (ImageId $id): ?ImageResponse => $found[$id->value()] ?? null,
            $ids,
        )));
    }
}
